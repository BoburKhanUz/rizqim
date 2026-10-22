<?php

namespace App\Domain\Pos\Shifts;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The owner's Telegram messages about a till (POS_SPEC 2.3, 2.10, 2.13): the short Z summary (takings by tender,
 * refunds, discounts, voids, expenses and the cash difference) and every refund. Sent like the other owner alerts: to
 * the store's admin chat, else to each owner with Telegram; after commit and queued (TelegramMessenger), so a Telegram
 * outage never fails the close or the refund.
 */
final class ShiftOwnerSummary
{
    public function __construct(
        private readonly TelegramMessenger $telegram,
    ) {}

    /**
     * @param  array<string, mixed>  $report  ShiftReport::build() at closing
     */
    public function send(PosShift $shift, array $report): void
    {
        $this->notify((int) $shift->store_id, $this->text($shift, $report));
    }

    /**
     * A refund within the shift (POS_SPEC 2.10: the owner hears about every refund).
     */
    public function refund(PosRefund $refund, User $actor, ?int $approvedBy): void
    {
        $uzs = number_format(Money::of($refund->amount)->toFloat(), 0, '.', ' ') . " so'm";
        $order = Order::find($refund->order_id);
        $approver = $approvedBy !== null ? User::find($approvedBy) : null;
        $methods = ['cash' => 'naqd', 'card' => 'karta', 'qr' => 'QR'];

        $this->notify((int) $refund->store_id, implode("\n", array_filter([
            "Pul qaytarildi: {$uzs} (" . ($methods[$refund->method] ?? $refund->method) . ')',
            $order !== null ? "Buyurtma {$order->short_code}" . ($order->queue_number ? " (navbat {$order->queue_number})" : '') : null,
            "Sabab: {$refund->reason}",
            "Kassir: {$actor->name}" . ($approver !== null ? ", tasdiqladi: {$approver->name}" : ''),
        ])));
    }

    /**
     * To the store's admin chat, else to each owner with Telegram; after the change commits; never fails the caller.
     */
    private function notify(int $storeId, string $text): void
    {
        DB::afterCommit(function () use ($storeId, $text) {
            try {
                if (!$this->telegram->isConfigured()) {
                    return;
                }

                $store = Store::find($storeId);
                if ($store === null) {
                    return;
                }

                $chatIds = filled($store->telegram_admin_chat_id)
                    ? [(string) $store->telegram_admin_chat_id]
                    : User::where('store_id', $store->id)->where('role', UserRole::OWNER->value)->get()
                        ->filter(fn (User $owner) => $owner->hasTelegram())
                        ->map(fn (User $owner) => (string) $owner->telegram_id)->values()->all();

                foreach ($chatIds as $chatId) {
                    $this->telegram->sendMessage($chatId, $text);
                }
            } catch (Throwable $e) {
                Log::error("Failed to send the POS shift alert of store {$storeId}: {$e->getMessage()}");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function text(PosShift $shift, array $report): string
    {
        $uzs = fn (mixed $amount) => number_format(Money::of(is_numeric($amount) ? (string) $amount : 0)->toFloat(), 0, '.', ' ') . " so'm";
        $branch = Branch::find($shift->branch_id);
        $tenders = ['cash' => 'Naqd', 'card' => 'Karta', 'qr' => 'QR', 'prepayment' => 'Avansdan', 'account' => 'Nasiya'];

        $lines = [
            "Z-hisobot #{$shift->z_number}" . ($shift->force_closed ? ' (majburan yopildi)' : ''),
            ($branch->name ?? '') . ', biznes kun ' . $shift->business_date->format('d.m.Y'),
            '',
            'Tushum: ' . $uzs($report['sales']['received'] ?? 0) . ' (' . (int) ($report['sales']['bills'] ?? 0) . ' ta schyot)',
        ];
        foreach ($report['by_tender'] ?? [] as $row) {
            if (Money::of($row['amount'])->isPositive()) {
                $lines[] = '  ' . ($tenders[$row['tender']] ?? $row['tender']) . ': ' . $uzs($row['amount']);
            }
        }
        $lines[] = 'Qaytarish: ' . $uzs($report['refunds']['amount'] ?? 0);
        $lines[] = 'Chegirma: ' . $uzs($report['discounts']['amount'] ?? 0);
        $lines[] = "O'chirilgan taomlar: " . (int) ($report['voids']['count'] ?? 0) . ' ta, ' . $uzs($report['voids']['amount'] ?? 0);
        $lines[] = 'Kassadan chiqim: ' . $uzs(Money::sum(
            $report['cash']['cash_out'] ?? 0,
            $report['cash']['wages'] ?? 0,
            $report['cash']['purchases'] ?? 0,
        )->toString());
        $lines[] = 'Kutilgan naqd: ' . $uzs($shift->expected_cash) . ', sanaldi: ' . $uzs($shift->counted_cash);
        $difference = Money::of($shift->difference);
        $lines[] = 'Farq: ' . ($difference->isNegative() ? '-' : ($difference->isPositive() ? '+' : '')) . $uzs($difference->abs()->toString());
        if ($shift->force_closed && filled($shift->close_note)) {
            $lines[] = 'Sabab: ' . $shift->close_note;
        }

        return implode("\n", $lines);
    }
}
