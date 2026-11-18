<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Support\Partitions\PartitionManager;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * M3 (docs/scale/M3_DESIGN.md section 8, step 5): the application's own check of a restored database copy, run by
 * scripts/restore-drill.sh against the scratch server (`DB_HOST=... DB_PORT=5499 DB_POOLER=direct`).
 *
 * Read-only: everything runs in one READ ONLY transaction that is rolled back, so the command cannot write even by
 * accident (a model event, a cast). Checks: no pending migration; the newest order with its items, bills and payments,
 * the newest fiscal receipt and one audit row load through the models; every journal table is partitioned with the
 * current month and a DEFAULT partition attached; the audit append-only trigger exists.
 *
 * Prints one Uzbek line per check (`OK: ...` / `XATO: ...`). Exit 0 = all passed, 1 = at least one failed.
 */
class RestoreSmokeCommand extends Command
{
    protected $signature = 'db:restore-smoke';

    protected $description = "Tiklangan baza nusxasini ilova tomonidan tekshiradi (faqat o'qiydi): migratsiyalar, buyurtma, chek, audit, bo'limlar";

    /** @var list<string> */
    private array $failures = [];

    public function handle(Migrator $migrator, PartitionManager $partitions): int
    {
        try {
            DB::beginTransaction();
            DB::statement('SET TRANSACTION READ ONLY');
        } catch (Throwable $e) {
            $this->line("XATO: bazaga ulanib bo'lmadi: " . $this->short($e));

            return self::FAILURE;
        }

        try {
            $this->check('migratsiyalar', fn () => $this->migrations($migrator));
            $this->check('buyurtma', fn () => $this->order());
            $this->check('fiskal chek', fn () => $this->fiscalReceipt());
            $this->check('audit jurnali', fn () => $this->audit());
            $this->check("jurnallarning oylik bo'limlari", fn () => $this->partitions($partitions));
            $this->check('audit himoyasi', fn () => $this->appendOnlyTrigger());
        } finally {
            DB::rollBack();
        }

        if ($this->failures !== []) {
            $this->line('Natija: ' . count($this->failures) . ' ta tekshiruv o\'tmadi.');

            return self::FAILURE;
        }
        $this->line("Natija: tiklangan nusxa ilova uchun yaroqli.");

        return self::SUCCESS;
    }

    /**
     * Runs one check in its own savepoint (a failed SQL statement does not abort the others).
     *
     * @param  callable(): string  $check returns the OK line's text, throws or returns "!..." on failure
     */
    private function check(string $name, callable $check): void
    {
        try {
            $result = DB::transaction(fn () => $check());
        } catch (Throwable $e) {
            $result = "!{$name}: " . $this->short($e);
        }

        if (str_starts_with($result, '!')) {
            $this->failures[] = $name;
            $this->line('XATO: ' . substr($result, 1));

            return;
        }
        $this->line("OK: {$result}");
    }

    private function migrations(Migrator $migrator): string
    {
        if (!$migrator->repositoryExists()) {
            return "!migratsiyalar jadvali yo'q: bu RIZQIM bazasi emas yoki tiklash chala";
        }
        $files = array_keys($migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')])));
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_diff($files, $ran));
        if ($pending !== []) {
            return '!' . count($pending) . " ta migratsiya bajarilmagan (masalan {$pending[0]}): nusxa ilova versiyasidan eski";
        }

        return 'migratsiyalar: hammasi bajarilgan (' . count($ran) . ' ta)';
    }

    private function order(): string
    {
        // The restored database's newest row of any store (a console command: no tenant, every scope off)
        $order = Order::query()->withoutGlobalScopes()->orderByDesc('id')->first();
        if ($order === null) {
            return "buyurtma: bazada buyurtma yo'q (yangi o'rnatish), o'qish ishladi";
        }
        $order->load(['items', 'bills', 'payments']);
        $order->toArray();

        return "buyurtma: oxirgisi #{$order->id} o'qildi: " . $order->items->count() . ' ta taom, ' . $order->bills->count() . ' ta hisob, ' . $order->payments->count() . " ta to'lov";
    }

    private function fiscalReceipt(): string
    {
        // The restored database's newest row of any store (a console command: no tenant, every scope off)
        $receipt = FiscalReceipt::query()->withoutGlobalScopes()->orderByDesc('id')->first();
        if ($receipt === null) {
            return "fiskal chek: bazada chek yo'q, o'qish ishladi";
        }
        $receipt->toArray();

        return "fiskal chek: oxirgisi #{$receipt->id} o'qildi (holati {$receipt->status->value})";
    }

    private function audit(): string
    {
        // The restored database's newest row of any store (a console command: no tenant, every scope off)
        $row = AuditLog::query()->withoutGlobalScopes()->orderByDesc('id')->first();
        if ($row === null) {
            return "audit jurnali: yozuv yo'q, o'qish ishladi";
        }
        $row->toArray();

        return "audit jurnali: oxirgi yozuv #{$row->id} o'qildi";
    }

    private function partitions(PartitionManager $partitions): string
    {
        $problems = [];
        $names = [];
        foreach (PartitionManager::tables() as $base => $config) {
            $names[] = $base;
            if (!$partitions->isPartitioned($base)) {
                $problems[] = "{$base} oylarga bo'linmagan";

                continue;
            }
            $list = $partitions->partitions($base);
            if (!in_array(true, array_column($list, 'default'), true)) {
                $problems[] = "{$base} da zaxira (DEFAULT) bo'lim yo'q";
            }
            $month = PartitionManager::monthStart(now())->format('Y-m');
            $months = array_map(fn ($p) => $p['from']->format('Y-m'), array_filter($list, fn ($p) => !$p['default']));
            if (!in_array($month, $months, true)) {
                $problems[] = "{$base} da joriy oy ({$month}) bo'limi yo'q";
            }
        }
        if ($problems !== []) {
            return "!jurnallarning oylik bo'limlari: " . implode('; ', $problems);
        }

        return "jurnallarning oylik bo'limlari: " . implode(', ', $names) . ' joyida';
    }

    private function appendOnlyTrigger(): string
    {
        $exists = DB::selectOne("SELECT count(*) AS n FROM pg_trigger WHERE tgrelid = to_regclass('audit_logs') AND tgname = 'audit_logs_append_only' AND NOT tgisinternal");
        if ((int) ($exists->n ?? 0) < 1) {
            return "!audit himoyasi: audit_logs_append_only triggeri yo'q: audit yozuvlarini o'zgartirish mumkin bo'lib qolgan";
        }

        return 'audit himoyasi: audit_logs_append_only triggeri joyida';
    }

    private function short(Throwable $e): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 200);
    }
}
