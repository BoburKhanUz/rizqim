<?php

namespace App\Services\Admin\Export\Datasets;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminPii;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSlaSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * P4-31: Buyurtmalar → Excel. The list's filters (status group, channel, region, store, sla, from/to, q) through the
 * list's own query (AdminOrderQuery). The customer's phone is masked unless admin.pii.view.
 */
final class OrdersExport extends ExportDataset
{
    public function __construct(
        private readonly AdminOrderQuery $orders,
        private readonly OrderSlaSettings $sla,
        private readonly AdminPii $pii,
    ) {}

    public function key(): string
    {
        return 'orders';
    }

    public function sheet(): string
    {
        return 'Buyurtmalar';
    }

    public function label(): string
    {
        return 'Buyurtmalar (marketpleys)';
    }

    public function rules(): array
    {
        $id = 'nullable|integer|min:1|max:999999999999999999';

        return [
            'status' => ['nullable', Rule::in(array_keys(AdminOrderQuery::GROUPS))],
            'channel' => ['nullable', Rule::in(AdminOrderQuery::channels())],
            'region_id' => $id,
            'store_id' => $id,
            'sla' => ['nullable', Rule::in(['late', 'warning'])],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => 'nullable|string|min:2|max:100',
        ];
    }

    public function headers(): array
    {
        return ['Kod', 'Holat', 'Kanal', 'Turi', 'Ovqatxona', 'Filial', 'Viloyat', 'Mijoz', 'Mijoz telefoni', "Summa (so'm)", "To'lov usuli", "To'lov holati", 'Kuryer', 'Yaratilgan', 'Yangilangan', 'UUID'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        return $this->orders->filter($this->orders->base(), $filters, $this->sla->limits(), true, $this->pii->canReveal($viewer));
    }

    public function keyColumn(): string
    {
        return 'orders.id';
    }

    public function with(): array
    {
        return ['store:id,name', 'branch:id,name,region_id', 'branch.region:id,name_uz', 'user:id,name,phone', 'courier:id,name'];
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var Order $order */
        $order = $record;

        return [
            $cells->text($order->short_code),
            $order->status->label(),
            $order->channel->label(),
            match ($order->order_type) {
                OrderType::DELIVERY => 'Yetkazish',
                OrderType::PICKUP => 'Olib ketish',
                OrderType::DINE_IN => 'Zalda',
            },
            $cells->text($order->store?->name),
            $cells->text($order->branch?->name),
            $cells->text($order->branch?->region?->name_uz),
            $cells->text($order->user?->name),
            $cells->phone($order->user?->phone),
            $cells->money($order->total_price),
            $cells->text($order->payment_method),
            match ($order->payment_status) {
                PaymentStatus::PENDING => 'Kutilmoqda',
                PaymentStatus::PAID => "To'langan",
                PaymentStatus::FAILED => 'Muvaffaqiyatsiz',
                PaymentStatus::CANCELLED => 'Bekor qilingan',
                PaymentStatus::EXPIRED => "Muddati o'tgan",
                PaymentStatus::REFUNDED => 'Qaytarilgan',
            },
            $cells->text($order->courier?->name),
            $cells->time($order->created_at),
            $cells->time($order->updated_at),
            (string) $order->uuid,
        ];
    }
}
