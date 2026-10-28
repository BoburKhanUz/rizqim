<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Order;
use App\Models\Region;
use App\Models\SubscriptionInvoice;
use App\Services\Admin\Today\PlatformStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * P4-13: helpers for the Bugun tests: orders and paid invoices at a given Asia/Tashkent time, regions.
 * Uses OrderLifecycleFixtures' store, branch and customer.
 */
trait AdminTodayFixtures
{
    /** "2026-11-10 14:30" in Asia/Tashkent */
    protected function tashkent(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, PlatformStats::TIMEZONE);
    }

    protected function at(string $local): void
    {
        // In UTC like the app clock, so timestamps written with now() are UTC wall time as in production
        $this->travelTo($this->tashkent($local)->setTimezone('UTC'));
    }

    protected function orderAt(string $local, OrderStatus $status = OrderStatus::DELIVERED, array $attributes = []): Order
    {
        $created = $this->tashkent($local)->setTimezone('UTC');

        return $this->makeOrder($status, array_merge([
            'created_at' => $created,
            'updated_at' => $created,
        ], $attributes));
    }

    protected function invoicePaidAt(?string $local, string $amount, SubscriptionInvoiceStatus $status = SubscriptionInvoiceStatus::PAID, array $attributes = []): SubscriptionInvoice
    {
        static $sequence = 0;
        $sequence++;

        return SubscriptionInvoice::forceCreate(array_merge([
            'number' => 'OB-TEST-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT) . Str::random(4),
            'store_id' => $this->store->id,
            // One invoice per store and period: a distinct made-up period each
            'period' => sprintf('%04d-01', 2000 + $sequence),
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'terminals_count' => 1,
            'included_terminals' => 1,
            'base_fee' => $amount,
            'fee_per_terminal' => '0',
            'lines' => [],
            'amount' => $amount,
            'status' => $status,
            'due_date' => now()->addDays(10)->toDateString(),
            'issued_at' => now()->subDays(5),
            'paid_at' => $local !== null ? $this->tashkent($local)->setTimezone('UTC') : null,
        ], $attributes));
    }

    protected function region(string $name): Region
    {
        return Region::forceCreate(['name_uz' => $name, 'is_active' => true]);
    }
}
