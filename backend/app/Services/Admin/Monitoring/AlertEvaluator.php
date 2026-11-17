<?php

namespace App\Services\Admin\Monitoring;

use App\Events\Broadcast\PlatformAlertBroadcast;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: the alert engine, run every minute by EvaluateAlertsJob. For each enabled rule its check lists the problems
 * that exist now; each becomes an alert (one unresolved alert per rule and subject: a problem that is still there only
 * updates `last_seen_at`, `count` and the details), and an unresolved alert whose problem is gone is resolved
 * automatically. A rule that is switched off resolves its alerts. A check that fails or cannot read its source
 * changes nothing (a broken measurement never "fixes" an alert). Opening and resolving are broadcast on
 * `platform.ops` and announced in the ops Telegram group (AlertNotifier).
 */
class AlertEvaluator
{
    public function __construct(
        private readonly Container $container,
        private readonly AlertNotifier $notifier,
    ) {}

    /**
     * @return array{opened: int, updated: int, resolved: int}
     */
    public function run(): array
    {
        $totals = ['opened' => 0, 'updated' => 0, 'resolved' => 0];
        // The run's Telegram budget starts with its first message (AlertNotifier), not before the checks
        $this->notifier->startRun();
        // The checks share a time cap (each SQL read has its own statement timeout): past it the remaining rules are
        // skipped this minute (unknown: their alerts stay as they are) so the announcements below still go out
        $started = microtime(true);
        $cap = max(5, (int) config('monitoring.checks_budget_seconds', 30));
        $skipped = [];

        foreach (AlertRule::ensureAll() as $rule) {
            if ($rule->enabled && microtime(true) - $started > $cap) {
                $skipped[] = $rule->kind;

                continue;
            }
            try {
                $result = $rule->enabled ? $this->evaluate($rule) : $this->switchedOff($rule);
            } catch (Throwable $e) {
                Log::error('Monitoring: alert rule failed', ['kind' => $rule->kind, 'exception' => $e::class, 'message' => $e->getMessage()]);

                continue;
            }

            foreach ($result as $key => $count) {
                $totals[$key] += $count;
            }
        }
        if ($skipped !== []) {
            Log::warning('Monitoring: the checks took too long, rules skipped this run', ['cap_seconds' => $cap, 'skipped' => $skipped]);
        }

        // This run's resolve notices (grouped per rule), then every unresolved alert not announced yet (new ones, raised
        // ones, ones a limit or an outage held back)
        try {
            $this->notifier->flushResolved();
            $this->notifier->announcePending();
        } catch (Throwable $e) {
            Log::error('Monitoring: alert announcements failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }

        return $totals;
    }

    /**
     * A team member resolves an alert by hand. Still open for a moment later if the problem persists: the
     * evaluator then opens a new alert. False when the alert was already resolved (by the evaluator or another
     * member) in the meantime: nothing is changed, announced or broadcast then.
     */
    public function resolveByMember(Alert $alert, User $member, ?string $note): bool
    {
        $resolved = $this->resolve($alert, 'manual', $member, $note);
        $this->notifier->flushResolved();

        return $resolved;
    }

    /**
     * Only an `open` alert can be acknowledged, decided by the database row (UPDATE ... WHERE state = 'open'), not by
     * the copy in memory: an alert the evaluator resolved a moment ago is never brought back (that would also collide
     * with the new alert the evaluator may have opened for the same subject). False when it was not open any more.
     */
    public function acknowledge(Alert $alert, User $member, ?string $note): bool
    {
        $values = [
            'state' => AlertState::ACKNOWLEDGED->value,
            'acknowledged_by' => $member->id,
            'acknowledged_at' => now(),
            'acknowledge_note' => $note,
            'updated_at' => now(),
        ];

        if (Alert::query()->whereKey($alert->getKey())->where('state', AlertState::OPEN->value)->update($values) !== 1) {
            return false;
        }

        $alert->forceFill($values)->syncOriginal();
        event(new PlatformAlertBroadcast($alert, PlatformAlertBroadcast::ACKNOWLEDGED));

        return true;
    }

    // ------------------------------------------------------------------------------------------------------------

    /**
     * @return array{opened: int, updated: int, resolved: int}
     */
    private function evaluate(AlertRule $rule): array
    {
        $kind = $rule->kindEnum();
        /** @var Checks\AlertCheck $check */
        $check = $this->container->make($kind->check());
        $findings = $check->findings($rule);
        if ($findings === null) {
            return ['opened' => 0, 'updated' => 0, 'resolved' => 0];
        }

        $result = ['opened' => 0, 'updated' => 0, 'resolved' => 0];
        $unresolved = Alert::query()->where('alert_rule_id', $rule->id)->unresolved()->get()->keyBy('subject_key');
        $seen = [];

        foreach ($findings as $finding) {
            $seen[$finding->subjectKey] = true;
            $existing = $unresolved[$finding->subjectKey] ?? null;

            if ($existing !== null) {
                $severity = $finding->severity ?? $kind->severity();
                $escalated = self::rank($severity) > self::rank($existing->severity);
                // Not when a member resolved it since the list was read: a resolved alert stays as it was closed
                $touched = Alert::query()->whereKey($existing->getKey())->where('state', '<>', AlertState::RESOLVED->value)->update([
                    'title' => mb_substr($finding->title, 0, 200),
                    'details' => json_encode($finding->details),
                    // A problem that got worse is raised (a warning that became critical); one that eased keeps its level
                    'severity' => $escalated ? $severity : $existing->severity,
                    'last_seen_at' => now(),
                    'count' => DB::raw('"count" + 1'),
                    'updated_at' => now(),
                ]);
                $result['updated'] += $touched;
                if ($touched === 1 && $escalated) {
                    // Announced again as what it is now (AlertNotifier::announcePending, at the end of the run); its first
                    // announcement stays, so its resolve notice still goes out
                    $this->notifier->markEscalated($existing);
                }

                continue;
            }

            try {
                $alert = Alert::query()->create([
                    'alert_rule_id' => $rule->id,
                    'kind' => $kind->value,
                    'subject_key' => $finding->subjectKey,
                    'severity' => $finding->severity ?? $kind->severity(),
                    'state' => AlertState::OPEN->value,
                    'title' => mb_substr($finding->title, 0, 200),
                    'details' => $finding->details,
                    'store_id' => $finding->storeId,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'count' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another evaluator run opened it a moment ago
                continue;
            }

            $result['opened']++;
            event(new PlatformAlertBroadcast($alert, PlatformAlertBroadcast::OPENED));
            // Announced by AlertNotifier::announcePending at the end of the run
        }

        foreach ($unresolved as $subject => $alert) {
            if (!isset($seen[$subject]) && $this->resolve($alert, 'auto')) {
                $result['resolved']++;
            }
        }

        return $result;
    }

    private static function rank(?string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            default => 1,
        };
    }

    /**
     * @return array{opened: int, updated: int, resolved: int}
     */
    private function switchedOff(AlertRule $rule): array
    {
        $resolved = 0;
        foreach (Alert::query()->where('alert_rule_id', $rule->id)->unresolved()->get() as $alert) {
            if ($this->resolve($alert, 'rule_disabled', null, "Qoida o'chirildi")) {
                $resolved++;
            }
        }

        return ['opened' => 0, 'updated' => 0, 'resolved' => $resolved];
    }

    /**
     * Resolves the alert if it is still unresolved in the database (UPDATE ... WHERE state <> 'resolved' and a check of
     * the affected rows): of a member and the evaluator racing, exactly one wins, and only the winner broadcasts and
     * tells the ops group.
     */
    private function resolve(Alert $alert, string $resolution, ?User $member = null, ?string $note = null): bool
    {
        $values = [
            'state' => AlertState::RESOLVED->value,
            'resolved_at' => now(),
            'resolved_by' => $member?->id,
            'resolve_note' => $note,
            'resolution' => $resolution,
            'updated_at' => now(),
        ];

        $won = DB::transaction(function () use ($alert, $values) {
            $affected = Alert::query()->whereKey($alert->getKey())->where('state', '<>', AlertState::RESOLVED->value)->update($values);
            if ($affected !== 1) {
                return false;
            }

            $alert->forceFill($values)->syncOriginal();
            event(new PlatformAlertBroadcast($alert, PlatformAlertBroadcast::RESOLVED));

            return true;
        });
        if (!$won) {
            return false;
        }

        // A rule that was just switched off is not announced: the people who switched it off know
        if ($resolution !== 'rule_disabled') {
            $this->notifier->resolved($alert, $member?->name);
        }

        return true;
    }
}
