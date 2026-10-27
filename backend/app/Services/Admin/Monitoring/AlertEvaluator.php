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

        foreach (AlertRule::ensureAll() as $rule) {
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

        return $totals;
    }

    /**
     * A team member resolves an alert by hand. Still open for a moment later if the problem persists: the
     * evaluator then opens a new alert.
     */
    public function resolveByMember(Alert $alert, User $member, ?string $note): void
    {
        $this->resolve($alert, 'manual', $member, $note);
    }

    public function acknowledge(Alert $alert, User $member, ?string $note): void
    {
        $alert->forceFill([
            'state' => AlertState::ACKNOWLEDGED->value,
            'acknowledged_by' => $member->id,
            'acknowledged_at' => now(),
            'acknowledge_note' => $note,
        ])->save();

        event(new PlatformAlertBroadcast($alert, PlatformAlertBroadcast::ACKNOWLEDGED));
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
                $existing->forceFill([
                    'title' => $finding->title,
                    'details' => $finding->details,
                    'last_seen_at' => now(),
                    'count' => $existing->count + 1,
                ])->save();
                $result['updated']++;

                continue;
            }

            try {
                $alert = Alert::query()->create([
                    'alert_rule_id' => $rule->id,
                    'kind' => $kind->value,
                    'subject_key' => $finding->subjectKey,
                    'severity' => $kind->severity(),
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
            $this->notifier->opened($alert, $rule);
        }

        foreach ($unresolved as $subject => $alert) {
            if (!isset($seen[$subject])) {
                $this->resolve($alert, 'auto');
                $result['resolved']++;
            }
        }

        return $result;
    }

    /**
     * @return array{opened: int, updated: int, resolved: int}
     */
    private function switchedOff(AlertRule $rule): array
    {
        $resolved = 0;
        foreach (Alert::query()->where('alert_rule_id', $rule->id)->unresolved()->get() as $alert) {
            $this->resolve($alert, 'rule_disabled', null, "Qoida o'chirildi");
            $resolved++;
        }

        return ['opened' => 0, 'updated' => 0, 'resolved' => $resolved];
    }

    private function resolve(Alert $alert, string $resolution, ?User $member = null, ?string $note = null): void
    {
        DB::transaction(function () use ($alert, $resolution, $member, $note) {
            $alert->forceFill([
                'state' => AlertState::RESOLVED->value,
                'resolved_at' => now(),
                'resolved_by' => $member?->id,
                'resolve_note' => $note,
                'resolution' => $resolution,
            ])->save();

            event(new PlatformAlertBroadcast($alert, PlatformAlertBroadcast::RESOLVED));
        });

        // A rule that was just switched off is not announced: the people who switched it off know
        if ($resolution !== 'rule_disabled') {
            $this->notifier->resolved($alert, $member?->name);
        }
    }
}
