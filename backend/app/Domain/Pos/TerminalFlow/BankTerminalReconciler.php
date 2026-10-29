<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Domain\Fiscal\Gateways\FiscalGatewayManager;
use App\Domain\Fiscal\Gateways\SmartKassaGateway;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Billing\PosActionException;
use App\Models\BankTerminalReport;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * End of day with a bank terminal (P6-16, D5 d): the bank terminal's day total of a shift — typed from its
 * settlement slip (`manual`), imported as its transactions (`import`), or asked from the smart kassa's provider
 * (`provider`, SmartKassaGateway::cardTotals) — is compared with the card money Rizqim recorded in the shift
 * (ShiftCardTotals). The difference (bank − Rizqim) above the branch's threshold (branch_terminal_setups) alerts the
 * owner (PosOwnerAlerts) and shows on the Z. Imported transactions are matched to Rizqim's by their RRN.
 *
 * It never blocks anything: a shift closes with or without it, and it can be entered or corrected after the Z. One
 * report per shift; a new entry replaces it (audited). A closed shift's report is worked out again at the close
 * (refresh) so an early entry compares with the whole shift.
 */
final class BankTerminalReconciler
{
    public const SOURCES = ['manual', 'import', 'provider'];

    public const MAX_TRANSACTIONS = 2000;

    public function __construct(
        private readonly ShiftCardTotals $cards,
        private readonly TerminalSetup $setup,
        private readonly PosOwnerAlerts $alerts,
        private readonly AuditLogger $audit,
        private readonly FiscalGatewayManager $gateways,
    ) {}

    public function forShift(PosShift $shift): ?BankTerminalReport
    {
        return BankTerminalReport::where('shift_id', $shift->id)->first();
    }

    /**
     * Records (or replaces) the shift's bank terminal report.
     *
     * @param  array{source?: string, bank_total?: string|null, bank_count?: int|null, terminal_ref?: string|null, transactions?: list<array{rrn?: string|null, amount: string}>|null, note?: string|null}  $input
     *
     * @throws PosActionException
     */
    public function record(PosShift $shift, array $input, User $actor, ?Device $device = null, ?\Closure $applied = null): BankTerminalReport
    {
        $source = (string) ($input['source'] ?? 'manual');
        if (!in_array($source, self::SOURCES, true)) {
            throw PosActionException::invalid('bank_source_invalid', "Manba noto'g'ri: manual, import yoki provider.");
        }

        $count = isset($input['bank_count']) ? (int) $input['bank_count'] : null;
        $transactions = null;
        if ($source === 'manual') {
            if (!isset($input['bank_total']) || $input['bank_total'] === '') {
                throw PosActionException::invalid('bank_total_required', 'Bank terminalining kunlik jami summasini kiriting.');
            }
            $total = Money::of((string) $input['bank_total']);
        } elseif ($source === 'import') {
            $transactions = $this->transactions((array) ($input['transactions'] ?? []));
            if ($transactions === []) {
                throw PosActionException::invalid('bank_transactions_required', "Import uchun bank terminali tranzaksiyalarini yuboring.");
            }
            $total = Money::sum(...array_column($transactions, 'amount'));
            $count = count($transactions);
        } else {
            [$total, $count, $transactions] = $this->fromProvider($shift);
        }

        $report = DB::transaction(function () use ($shift, $source, $total, $count, $transactions, $input, $actor, $device, $applied) {
            PosShift::whereKey($shift->id)->lockForUpdate()->first();
            $existing = BankTerminalReport::where('shift_id', $shift->id)->first();
            $before = $existing !== null ? $this->auditView($existing) : [];

            $report = $existing ?? new BankTerminalReport([
                'store_id' => $shift->store_id,
                'branch_id' => $shift->branch_id,
                'shift_id' => $shift->id,
            ]);
            $report->forceFill([
                'business_date' => $shift->business_date,
                'source' => $source,
                'terminal_ref' => filled($input['terminal_ref'] ?? null) ? mb_substr(trim((string) $input['terminal_ref']), 0, 64) : null,
                'bank_total' => $total->toString(),
                'bank_count' => $count,
                'transactions' => $transactions,
                'note' => filled($input['note'] ?? null) ? mb_substr(trim((string) $input['note']), 0, 500) : null,
                'entered_by' => $actor->id,
                'device_id' => $device?->id,
            ]);
            $alert = $this->compare($report, $shift);
            $report->save();

            $this->audit->record('pos.bank_terminal_report', $shift, $before, $this->auditView($report), [], [
                'actor' => $actor,
                'device' => $device,
                'store_id' => (int) $shift->store_id,
                'branch_id' => (int) $shift->branch_id,
            ]);

            if ($alert) {
                $this->alert($report, $shift, $actor);
            }
            if ($applied !== null) {
                $applied();
            }

            return $report;
        });

        return $report->refresh();
    }

    /**
     * The shift's report worked out again (its card money may have changed since it was entered: a later card
     * payment, a refund, the close). Alerts when it newly goes over the threshold. Null when the shift has none.
     */
    public function refresh(PosShift $shift): ?BankTerminalReport
    {
        return DB::transaction(function () use ($shift) {
            $report = BankTerminalReport::where('shift_id', $shift->id)->lockForUpdate()->first();
            if ($report === null) {
                return null;
            }
            $alert = $this->compare($report, $shift);
            $report->save();
            if ($alert) {
                $this->alert($report, $shift, null);
            }

            return $report;
        });
    }

    /**
     * Fills Rizqim's side, the difference, the threshold and the RRN matching in; true when the owner is to be told
     * (over the threshold now, and not already told about this very difference).
     */
    private function compare(BankTerminalReport $report, PosShift $shift): bool
    {
        $cards = $this->cards->of($shift);
        $branch = Branch::find($shift->branch_id);
        $threshold = $branch !== null ? $this->setup->threshold($branch) : Money::zero();
        $difference = Money::of($report->bank_total)->minus($cards['total']);
        $over = $difference->abs()->isGreaterThan($threshold);

        $previous = $report->exists && $report->alerted_at !== null ? Money::of($report->getOriginal('difference') ?? '0') : null;

        $report->forceFill([
            'rizqim_total' => $cards['total']->toString(),
            'rizqim_count' => $cards['count'],
            'rizqim_breakdown' => $cards['breakdown'],
            'difference' => $difference->toString(),
            'threshold' => $threshold->toString(),
            'over_threshold' => $over,
            'unmatched' => is_array($report->transactions) ? $this->match($report, $cards['transactions']) : null,
        ]);

        $alert = $over && ($previous === null || !$previous->equals($difference));
        if ($alert) {
            $report->alerted_at = now();
        }

        return $alert;
    }

    /**
     * Pairs bank transactions with Rizqim's by RRN (ShiftCardTotals::normalizeRrn); marks each bank row and lists what
     * is left on either side.
     *
     * @param  list<array{rrn: string|null, amount: string, kind: string}>  $ours
     * @return array{bank: list<array{rrn: string|null, amount: string}>, rizqim: list<array{rrn: string|null, amount: string, kind: string}>}
     */
    private function match(BankTerminalReport $report, array $ours): array
    {
        $pool = [];
        foreach ($ours as $k => $row) {
            $key = ShiftCardTotals::normalizeRrn($row['rrn']);
            if ($key !== null) {
                $pool[$key][] = $k;
            }
        }

        $rows = [];
        $unmatchedBank = [];
        foreach ((array) $report->transactions as $row) {
            $key = ShiftCardTotals::normalizeRrn($row['rrn'] ?? null);
            $hit = null;
            if ($key !== null && !empty($pool[$key])) {
                foreach ($pool[$key] as $i => $k) {
                    if (Money::of($ours[$k]['amount'])->equals(Money::of($row['amount']))) {
                        $hit = $k;
                        unset($pool[$key][$i]);
                        break;
                    }
                }
            }
            $rows[] = ['rrn' => $row['rrn'] ?? null, 'amount' => Money::of($row['amount'])->toString(), 'matched' => $hit !== null];
            if ($hit !== null) {
                unset($ours[$hit]);
            } else {
                $unmatchedBank[] = ['rrn' => $row['rrn'] ?? null, 'amount' => Money::of($row['amount'])->toString()];
            }
        }
        $report->transactions = $rows;

        return ['bank' => $unmatchedBank, 'rizqim' => array_values($ours)];
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{rrn: string|null, amount: string, matched: bool}>
     *
     * @throws PosActionException
     */
    private function transactions(array $rows): array
    {
        if (count($rows) > self::MAX_TRANSACTIONS) {
            throw PosActionException::invalid('bank_transactions_too_many', 'Bitta hisobotda ko\'pi bilan ' . self::MAX_TRANSACTIONS . ' ta tranzaksiya.');
        }

        $out = [];
        foreach (array_values($rows) as $i => $row) {
            $amount = is_array($row) ? ($row['amount'] ?? null) : null;
            if (!is_scalar($amount) || !preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $amount)) {
                throw PosActionException::invalid('bank_transaction_invalid', ($i + 1) . "-tranzaksiya summasi noto'g'ri.", ['index' => $i]);
            }
            $rrn = is_scalar($row['rrn'] ?? null) ? mb_substr(trim((string) $row['rrn']), 0, 64) : '';
            $out[] = ['rrn' => $rrn !== '' ? $rrn : null, 'amount' => Money::of((string) $amount)->toString(), 'matched' => false];
        }

        return $out;
    }

    /**
     * The smart kassa provider's own card total for the shift's till (SmartKassaGateway::cardTotals).
     *
     * @return array{0: Money, 1: int, 2: list<array{rrn: string|null, amount: string, matched: bool}>}
     *
     * @throws PosActionException
     */
    private function fromProvider(PosShift $shift): array
    {
        $device = $shift->device_id !== null ? Device::find($shift->device_id) : null;
        $store = Store::find($shift->store_id);
        if ($device === null || $store === null || !$device->getAttribute('smart_kassa')) {
            throw PosActionException::conflict('provider_report_unavailable', "Bu kassa smart-kassa emas: bank terminali hisobotini qo'lda kiriting.");
        }

        $gateway = $this->gateways->driver(FiscalSettings::of($store)->smartKassaDriver());
        $totals = $gateway instanceof SmartKassaGateway
            ? $gateway->cardTotals($device, $store, $shift->opened_at->toImmutable(), ($shift->closed_at ?? now())->toImmutable())
            : null;
        if ($totals === null) {
            throw PosActionException::conflict('provider_report_unavailable', "Fiskal provayder kunlik karta hisobotini bermadi: uni qo'lda kiriting.");
        }

        return [
            $totals->total,
            $totals->count,
            array_map(fn (array $row) => ['rrn' => $row['rrn'], 'amount' => $row['amount'], 'matched' => false], $totals->transactions),
        ];
    }

    private function alert(BankTerminalReport $report, PosShift $shift, ?User $actor): void
    {
        $branch = Branch::find($shift->branch_id);
        $difference = Money::of($report->difference);
        $sign = $difference->isNegative() ? '-' : '+';

        $this->alerts->send((int) $shift->store_id, implode("\n", array_filter([
            "🏦 Bank terminali va Z mos emas: farq {$sign}" . PosOwnerAlerts::sum($difference->abs()),
            $branch?->name ? "Filial: {$branch->name}" : null,
            ($shift->z_number !== null ? "Z #{$shift->z_number}" : 'Smena (ochiq)') . ', biznes kun ' . $shift->business_date->format('d.m.Y'),
            'Bank terminali: ' . PosOwnerAlerts::sum($report->bank_total) . ', Rizqim (karta): ' . PosOwnerAlerts::sum($report->rizqim_total),
            'Chegara: ' . PosOwnerAlerts::sum($report->threshold),
            $actor !== null ? "Kiritdi: {$actor->name}" : null,
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditView(BankTerminalReport $report): array
    {
        return [
            'source' => $report->source,
            'bank_total' => Money::of($report->bank_total)->toString(),
            'bank_count' => $report->bank_count,
            'terminal_ref' => $report->terminal_ref,
            'rizqim_total' => Money::of($report->rizqim_total)->toString(),
            'difference' => Money::of($report->difference)->toString(),
            'over_threshold' => (bool) $report->over_threshold,
            'note' => $report->note,
        ];
    }
}
