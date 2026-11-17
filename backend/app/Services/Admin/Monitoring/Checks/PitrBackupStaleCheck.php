<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;

/**
 * M3: with PITR on, the backup service's `pitr` marker (every 5 minutes) must be fresh (`window_minutes`), the last
 * backup of any type younger than `threshold` hours and the last full one younger than `threshold_secondary` days;
 * pgBackRest itself must say ok, and an off-site copy that was configured must work. No off-site copy at all (the
 * marker's `offsite: false`) is a standing warning (`offsite_missing`): it stays open until S3 is set up. With PITR
 * off: quiet.
 */
class PitrBackupStaleCheck implements AlertCheck
{
    public function __construct(
        private readonly BackupState $backups,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, (int) ($rule->window_minutes ?? BackupState::MARKER_MAX_AGE_MINUTES));
        $marker = $this->backups->marker(MonitoringMarker::PITR);
        if (!$this->backups->pitrEnabled($marker)) {
            return [];
        }

        $age = $this->backups->markerAgeSeconds($marker);
        if ($marker === null || $age > $window * 60) {
            // A fresh install: the backup service gets the rule's window to report for the first time
            if ($marker === null && $rule->created_at !== null && $rule->created_at->gt(now()->subMinutes($window))) {
                return [];
            }

            return [new AlertFinding('marker', $marker === null
                ? "Zaxira xizmati uzluksiz zaxira holatini hech qachon yozmagan"
                : 'Zaxira xizmati ' . intdiv((int) $age, 60) . " daqiqadan beri uzluksiz zaxira holatini yozmayapti: backup xizmati to'xtagan bo'lishi mumkin", [
                    'marker_at' => $marker?->recorded_at->toIso8601String(),
                    'marker_age_seconds' => $age,
                    'threshold_minutes' => $window,
                ])];
        }

        $meta = (array) $marker->meta;
        $findings = [];
        if (($meta['enabled'] ?? null) === false) {
            return [new AlertFinding('status', "PITR yoqilgan (PITR_ENABLED), lekin zaxira xizmatida o'chiq: backup xizmatini yangi sozlama bilan qayta ishga tushiring", [
                'enabled' => false,
            ])];
        }
        if (($meta['status'] ?? 'ok') !== 'ok') {
            // pgBackRest's own text can name hosts and buckets: it stays in the details (control center), not in the
            // title that goes to Telegram
            $findings[] = new AlertFinding('status', "Uzluksiz zaxira xato holatda: zaxira olinmayapti yoki tekshirib bo'lmayapti, xato matni boshqaruv markazida", [
                'status' => $meta['status'] ?? null,
                'message' => isset($meta['message']) ? mb_substr((string) $meta['message'], 0, 300) : null,
            ]);
        }

        $last = BackupState::parseTime($meta['last_backup_at'] ?? null);
        $full = BackupState::parseTime($meta['last_full_at'] ?? null);
        if ($last === null) {
            $findings[] = new AlertFinding('any', 'Uzluksiz zaxirada hali bitta ham nusxa yo\'q', ['threshold_hours' => $rule->threshold]);
        } elseif ($last->lt(now()->subHours($rule->threshold))) {
            $findings[] = new AlertFinding('any', 'Uzluksiz zaxiraning oxirgi nusxasi ' . (int) floor($last->diffInHours(now(), true)) . ' soat oldin olingan', [
                'last_backup_at' => $last->toIso8601String(),
                'last_backup_type' => $meta['last_backup_type'] ?? null,
                'threshold_hours' => $rule->threshold,
            ]);
        }
        $fullDays = $rule->threshold_secondary ?? 8;
        if ($last !== null && ($full === null || $full->lt(now()->subDays($fullDays)))) {
            $findings[] = new AlertFinding('full', $full === null
                ? "Uzluksiz zaxirada to'liq nusxa yo'q"
                : "Oxirgi to'liq zaxira " . (int) floor($full->diffInDays(now(), true)) . ' kun oldin olingan', [
                    'last_full_at' => $full?->toIso8601String(),
                    'threshold_days' => $fullDays,
                ]);
        }

        $offsite = $meta['repo2'] ?? null;
        if (in_array($offsite, ['no_cipher', 'partial', 'bad_endpoint'], true)) {
            $findings[] = new AlertFinding('offsite', match ($offsite) {
                'no_cipher' => "Tashqi nusxa (S3) yoqilmadi: PITR_CIPHER_PASS berilmagan",
                'partial' => "Tashqi nusxa (S3) sozlamasi chala: PITR_S3_* qiymatlarining bir qismi berilmagan",
                default => "Tashqi nusxa (S3) manzili noto'g'ri: PITR_S3_ENDPOINT ni tekshiring",
            }, ['repo2' => $offsite, 'note' => isset($meta['repo2_note']) ? mb_substr((string) $meta['repo2_note'], 0, 200) : null]);
        }
        // Working, but the only copy is on the database's own disk: a standing reminder (not paged as critical)
        if (($meta['offsite'] ?? null) === false && !in_array($offsite, ['no_cipher', 'partial', 'bad_endpoint'], true)) {
            $findings[] = new AlertFinding('offsite_missing', "Zaxira faqat shu serverda: server diski buzilsa, baza ham, barcha zaxiralar ham yo'qoladi. S3 kalitlarini (PITR_S3_*) yozing (OPERATIONS.md, 17-bo'lim)", [
                'repo2' => $offsite,
                'note' => isset($meta['repo2_note']) ? mb_substr((string) $meta['repo2_note'], 0, 200) : null,
            ], null, 'warning');
        }

        foreach ((array) ($meta['repos'] ?? []) as $repo) {
            if (is_array($repo) && ($repo['status'] ?? 'ok') !== 'ok') {
                $key = (int) ($repo['key'] ?? 0);
                $findings[] = new AlertFinding("repo{$key}", ($key === 2 ? 'Tashqi (S3)' : 'Mahalliy') . " zaxira ombori xato holatda: xato matni boshqaruv markazida", [
                    'repo' => $key,
                    'message' => mb_substr((string) ($repo['message'] ?? ''), 0, 300),
                ]);
            }
        }

        return $findings;
    }
}
