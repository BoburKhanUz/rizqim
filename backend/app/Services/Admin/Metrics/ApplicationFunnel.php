<?php

namespace App\Services\Admin\Metrics;

use App\Enums\StoreApplicationStage;
use App\Models\AuditLog;
use App\Models\StoreApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * P4-31 Ko'rsatkichlar: the conversion funnel of store applications (P4-22 pipeline) created in the last N months,
 * ariza → tekshiruv → menyu → qurilmalar → o'qitish → sinov → faol:
 *
 *  - reached: how many applications got to the stage or further (a move forward passes every stage between, as the
 *    checklists demand), so ariza = every application; share = reached / all, in percent with one decimal;
 *  - median_days: the median time applications spent IN the stage before they were moved out (to any stage, or
 *    rejected), in days with one decimal; null when no application has left it yet;
 *  - the totals: active, rejected, still open, conversion (active / all) and the median days from the application to
 *    "faol".
 *
 * The stage moves come from the audit log (admin.stores.applications.* entries about the application: the stage after
 * the change and when), the first stage from `created_at`; an application with no entries (seeded, imported) is read
 * from its current stage and `stage_changed_at`.
 */
class ApplicationFunnel
{
    private const SECONDS_IN_DAY = 86400;

    /**
     * @return array<string, mixed>
     */
    public function build(int $months, ?CarbonImmutable $now = null): array
    {
        $boundaries = MetricsMonths::boundaries($months, $now);
        $from = $boundaries[0]->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');

        /** @var Collection<int, StoreApplication> $applications */
        $applications = StoreApplication::query()
            ->where('created_at', '>=', $from)
            ->get(['id', 'stage', 'created_at', 'stage_changed_at', 'activated_at', 'rejected_at']);

        $moves = $this->moves($applications);

        $reached = array_fill_keys(StoreApplicationStage::values(), 0);
        $durations = [];
        $toActive = [];
        $counts = ['active' => 0, 'rejected' => 0, 'open' => 0];

        foreach ($applications as $application) {
            $timeline = $this->timeline($application, $moves[$application->id] ?? []);

            $furthest = 0;
            foreach ($timeline as [$stage]) {
                $furthest = max($furthest, $stage->position());
            }
            foreach (StoreApplicationStage::pipeline() as $stage) {
                if ($stage->position() <= $furthest) {
                    $reached[$stage->value]++;
                }
            }

            for ($i = 0; $i < count($timeline) - 1; $i++) {
                [$stage, $enteredAt] = $timeline[$i];
                $durations[$stage->value][] = max(0, $timeline[$i + 1][1]->getTimestamp() - $enteredAt->getTimestamp());
            }

            if ($application->stage === StoreApplicationStage::ACTIVE) {
                $counts['active']++;
                $activatedAt = $application->activated_at ?? $this->enteredAt($timeline, StoreApplicationStage::ACTIVE);
                if ($activatedAt !== null) {
                    $toActive[] = max(0, $activatedAt->getTimestamp() - $application->created_at->getTimestamp());
                }
            } elseif ($application->stage === StoreApplicationStage::REJECTED) {
                $counts['rejected']++;
            } else {
                $counts['open']++;
            }
        }

        $total = $applications->count();
        $stages = [];
        foreach (StoreApplicationStage::pipeline() as $stage) {
            $stages[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'reached' => $reached[$stage->value],
                'share' => $this->percent($reached[$stage->value], $total),
                'median_days' => $stage === StoreApplicationStage::ACTIVE ? null : $this->medianDays($durations[$stage->value] ?? []),
                'samples' => $stage === StoreApplicationStage::ACTIVE ? 0 : count($durations[$stage->value] ?? []),
            ];
        }

        return [
            'months' => $months,
            'from' => MetricsMonths::key($boundaries[0]),
            'applications' => $total,
            'active' => $counts['active'],
            'rejected' => $counts['rejected'],
            'open' => $counts['open'],
            'conversion' => $this->percent($counts['active'], $total),
            'median_days_to_active' => $this->medianDays($toActive),
            'stages' => $stages,
        ];
    }

    /**
     * The stage entries of the audit log per application, oldest first: [stage, at].
     *
     * @param  Collection<int, StoreApplication>  $applications
     * @return array<int, list<array{0: StoreApplicationStage, 1: CarbonImmutable}>>
     */
    private function moves(Collection $applications): array
    {
        $moves = [];
        $type = (new StoreApplication())->getMorphClass();

        foreach ($applications->pluck('id')->chunk(500) as $ids) {
            AuditLog::query()
                ->where('model_type', $type)
                ->whereIn('model_id', $ids->all())
                ->where('action', 'like', 'admin.stores.applications.%')
                ->orderBy('id')
                ->get(['model_id', 'after', 'created_at'])
                ->each(function (AuditLog $log) use (&$moves) {
                    $stage = is_array($log->after) ? StoreApplicationStage::tryFrom((string) ($log->after['stage'] ?? '')) : null;
                    if ($stage !== null) {
                        $moves[(int) $log->model_id][] = [$stage, CarbonImmutable::instance($log->created_at)];
                    }
                });
        }

        return $moves;
    }

    /**
     * Where the application was when: it starts in "ariza" at creation and follows its logged moves.
     *
     * @param  list<array{0: StoreApplicationStage, 1: CarbonImmutable}>  $moves
     * @return list<array{0: StoreApplicationStage, 1: CarbonImmutable}>
     */
    private function timeline(StoreApplication $application, array $moves): array
    {
        $created = CarbonImmutable::instance($application->created_at);
        $timeline = [[StoreApplicationStage::APPLICATION, $created]];

        foreach ($moves as [$stage, $at]) {
            // The creation entry itself says "ariza" again
            if ($stage === StoreApplicationStage::APPLICATION && count($timeline) === 1 && $at->getTimestamp() - $created->getTimestamp() < 60) {
                continue;
            }
            $timeline[] = [$stage, $at];
        }

        // Nothing logged for a moved application: the row is all there is
        $last = $timeline[count($timeline) - 1][0];
        if ($last !== $application->stage && $application->stage !== StoreApplicationStage::APPLICATION) {
            $at = $application->stage === StoreApplicationStage::ACTIVE ? ($application->activated_at ?? $application->stage_changed_at)
                : ($application->stage === StoreApplicationStage::REJECTED ? ($application->rejected_at ?? $application->stage_changed_at) : $application->stage_changed_at);
            $timeline[] = [$application->stage, CarbonImmutable::instance($at ?? $created)];
        }

        return $timeline;
    }

    /**
     * @param  list<array{0: StoreApplicationStage, 1: CarbonImmutable}>  $timeline
     */
    private function enteredAt(array $timeline, StoreApplicationStage $stage): ?CarbonImmutable
    {
        foreach ($timeline as [$entered, $at]) {
            if ($entered === $stage) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $seconds
     */
    private function medianDays(array $seconds): ?float
    {
        if ($seconds === []) {
            return null;
        }
        sort($seconds);
        $count = count($seconds);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $seconds[$middle] : ($seconds[$middle - 1] + $seconds[$middle]) / 2;

        return round($median / self::SECONDS_IN_DAY, 1);
    }

    private function percent(int $part, int $whole): ?string
    {
        return $whole > 0 ? number_format($part * 100 / $whole, 1, '.', '') : null;
    }
}
