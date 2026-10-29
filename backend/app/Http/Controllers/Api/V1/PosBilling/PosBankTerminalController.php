<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\TerminalFlow\BankTerminalPresenter;
use App\Domain\Pos\TerminalFlow\BankTerminalReconciler;
use App\Models\Branch;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-16 (D5 d): the bank terminal's day total of a shift at the till, compared with the card money Rizqim recorded
 * in it (BankTerminalReconciler). Behind the `terminal_card_flow` module; it never blocks closing the shift.
 */
#[Group('POS: billing', "Kassa: schyot, bo'lish, to'lov, qaytarish, smena, X/Z va menejer tasdig'i (P3-18).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosBankTerminalController extends PosBillingController
{
    /**
     * Bank terminal's day total of a shift
     *
     * The bank terminal's settlement (day) total, compared with the card money Rizqim recorded in the shift: card
     * payments of bills, card booking deposits and nasiya repayments taken on the till, less card refunds and card
     * deposit refunds (`rizqim_total`, `rizqim_breakdown`). `difference` = bank − Rizqim; above the branch's
     * `difference_threshold` (`over_threshold`) the owner is alerted and the Z shows it. `source`: `manual` (default:
     * `bank_total` typed from the slip, optional `bank_count`), `import` (`transactions`: [{rrn, amount}] from the
     * bank's report, up to 2000: the total is their sum and each is matched to Rizqim's card payments by RRN —
     * `unmatched.bank` / `unmatched.rizqim`), `provider` (a smart kassa: the provider's own card total; 409
     * `provider_report_unavailable` when there is none). Works on an open shift (compared again at its close) and
     * after the Z; a new entry replaces the earlier one (audited). Needs pos.close_shift; 403 `module_disabled`
     * without the terminal_card_flow module.
     */
    #[BodyParameter('source', description: 'manual | import | provider (manual by default).', required: false, type: "'manual'|'import'|'provider'")]
    #[BodyParameter('bank_total', description: "manual: the bank terminal's day total (decimal string).", required: false, type: 'string')]
    #[BodyParameter('bank_count', description: 'manual: how many card payments the slip lists.', required: false, type: 'int')]
    #[BodyParameter('transactions', description: "import: the bank report's rows.", required: false, infer: false, type: 'list<array{rrn?: string|null, amount: string}>')]
    #[BodyParameter('terminal_ref', description: "The bank terminal's id or name (optional).", required: false, type: 'string')]
    #[BodyParameter('note', required: false, type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BankTerminalPresenter::REPORT . '}')]
    public function update(Request $request, BankTerminalReconciler $reconciler, PosModules $modules, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate(self::rules());
        $shift = $this->shift($user, $uuid);

        // The middleware checked the caller's branch; a manager's sign-in may name another branch's shift
        $modules->assertEnabled(Branch::findOrFail($shift->branch_id), PosModule::TERMINAL_CARD_FLOW);

        $report = $reconciler->record($shift, self::input($data), $user, $this->terminal($user), $this->applied($request));

        return $this->success(
            BankTerminalPresenter::present($report, $shift),
            $report->over_threshold ? "Saqlandi. Bank terminali va Z mos emas: egasiga xabar yuborildi." : 'Saqlandi.',
        );
    }

    /**
     * The validation of a bank terminal report (the till's and the panel's).
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'source' => ['nullable', 'string', Rule::in(BankTerminalReconciler::SOURCES)],
            // Money as a decimal string (a JSON number passes too): at most 10 digits before the point
            'bank_total' => ['nullable', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/'],
            'bank_count' => 'nullable|integer|min:0|max:100000',
            'transactions' => 'nullable|array|max:' . BankTerminalReconciler::MAX_TRANSACTIONS,
            'transactions.*' => 'array',
            'transactions.*.rrn' => 'nullable|string|max:64',
            'transactions.*.amount' => ['required', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/'],
            'terminal_ref' => 'nullable|string|max:64',
            'note' => 'nullable|string|max:500',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{source: string, bank_total: string|null, bank_count: int|null, terminal_ref: string|null, transactions: list<array{rrn: string|null, amount: string}>|null, note: string|null}
     */
    public static function input(array $data): array
    {
        return [
            'source' => (string) ($data['source'] ?? 'manual'),
            'bank_total' => isset($data['bank_total']) ? (string) $data['bank_total'] : null,
            'bank_count' => isset($data['bank_count']) ? (int) $data['bank_count'] : null,
            'terminal_ref' => isset($data['terminal_ref']) ? (string) $data['terminal_ref'] : null,
            'transactions' => isset($data['transactions']) ? array_values(array_map(fn (array $row) => [
                'rrn' => isset($row['rrn']) ? (string) $row['rrn'] : null,
                'amount' => (string) $row['amount'],
            ], (array) $data['transactions'])) : null,
            'note' => isset($data['note']) ? (string) $data['note'] : null,
        ];
    }
}
