<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payment URLs of still-open Click/Payme orders were built with the platform's merchant ids whenever the store could
     * not collect that provider itself, yet those orders were STORE-owned. Make them platform-collected so the webhook
     * key and the settlement follow where the money actually goes (same rule as CreateOrderAction now).
     */
    public function up(): void
    {
        $orders = DB::table('orders')
            ->whereIn('payment_method', ['click', 'payme'])
            ->where('payment_owner', '!=', 'PLATFORM')
            ->whereNotIn('status', ['delivered', 'canceled', 'rejected', 'refunded'])
            ->whereNull('deleted_at')
            ->get(['id', 'store_id', 'payment_method']);

        if ($orders->isEmpty()) {
            return;
        }

        $stores = Store::withTrashed()->whereIn('id', $orders->pluck('store_id')->unique())->get()->keyBy('id');

        $platformCollected = $orders
            ->reject(fn ($order) => $stores->get($order->store_id)?->collectsOwnPayments($order->payment_method) ?? false)
            ->pluck('id');

        foreach ($platformCollected->chunk(500) as $ids) {
            DB::table('orders')->whereIn('id', $ids->all())->update(['payment_owner' => 'PLATFORM']);
        }
    }

    public function down(): void
    {
        // Data fix: the previous owner did not match where the money went, so it is not restored.
    }
};
