<?php

namespace Tests\Feature\AdminPos;

use App\Enums\DeviceType;
use App\Enums\InvoiceKind;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Str;
use Tests\Feature\AdminFixtures;
use Tests\Feature\FiscalFixtures;

/**
 * P4-24: members and sign-in (AdminFixtures) over the store set of the fiscal tests (two stores, branches, an owner),
 * plus invoices, devices, shifts and offline operations written directly.
 */
trait AdminPosFixtures
{
    use AdminFixtures;
    use FiscalFixtures;

    protected function invoice(array $attributes = [], ?Store $store = null): SubscriptionInvoice
    {
        static $sequence = 0;
        $sequence++;

        return SubscriptionInvoice::forceCreate(array_merge([
            'number' => 'OB-T-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'store_id' => ($store ?? $this->store)->id,
            'period' => sprintf('%04d-%02d', 2020 + intdiv($sequence, 12), $sequence % 12 + 1),
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'terminals_count' => 1,
            'included_terminals' => 1,
            'base_fee' => '249000.00',
            'fee_per_terminal' => '0',
            'lines' => [['type' => 'base', 'label' => 'Asosiy tarif', 'quantity' => 1, 'unit_price' => '249000.00', 'amount' => '249000.00']],
            'amount' => '249000.00',
            'paid_amount' => '0.00',
            'status' => SubscriptionInvoiceStatus::ISSUED,
            'kind' => InvoiceKind::MONTHLY,
            'sequence' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'issued_at' => now()->subDays(2),
        ], $attributes));
    }

    protected function device(array $attributes = [], ?Store $store = null): Device
    {
        return Device::forceCreate(array_merge([
            'store_id' => ($store ?? $this->store)->id,
            'branch_id' => $this->branch->id,
            'type' => DeviceType::POS->value,
            'name' => 'Kassa 1',
            'app_version' => '1.4.0',
            'platform' => 'android',
            'last_seen_at' => now()->subMinutes(2),
        ], $attributes));
    }

    protected function shift(array $attributes = []): PosShift
    {
        return PosShift::forceCreate(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'device_id' => null,
            'business_date' => now()->toDateString(),
            'status' => 'closed',
            'opened_by' => $this->manager->id,
            'opened_at' => now()->subHours(9),
            'opening_cash' => '100000.00',
            'closed_by' => $this->manager->id,
            'closed_at' => now()->subHour(),
            'expected_cash' => '500000.00',
            'counted_cash' => '500000.00',
            'difference' => '0.00',
            'z_number' => null,
        ], $attributes));
    }

    protected function syncOp(Device $device, string $status = 'rejected', array $attributes = []): PosSyncOperation
    {
        static $seq = 0;
        $seq++;

        return PosSyncOperation::forceCreate(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'device_id' => $device->id,
            'op_id' => (string) Str::uuid(),
            'seq' => $seq,
            'type' => 'bill.pay',
            'payload' => ['order' => 'o1', 'amount' => '50000', 'customer_phone' => '+998901234567', 'card' => ['number' => '8600']],
            'status' => $status,
            'reason' => 'order_cancelled',
            'error' => "Buyurtma bekor qilingan",
            'received_at' => now()->subHour(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function stepped(\App\Models\User $member, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->adminJson($method, $uri, $this->confirmedSession($member), $data);
    }
}
