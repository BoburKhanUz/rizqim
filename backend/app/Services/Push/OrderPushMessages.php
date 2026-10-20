<?php

namespace App\Services\Push;

use App\Models\Order;

/**
 * The words of the order pushes (Uzbek) and the data their apps open the order with. Short on purpose: the full text
 * is in the app, a push only gets the person there.
 */
class OrderPushMessages
{
    /**
     * What the customer is told when the order reaches its current status.
     */
    public function forCustomer(Order $order): PushContent
    {
        $status = $this->status($order);
        $code = "#{$order->short_code}";
        $delivery = $order->type === 'delivery';

        [$title, $body] = match ($status) {
            'new' => ['Buyurtma qabul qilindi', "Buyurtmangiz {$code} yuborildi, do'kon tasdiqlashini kuting."],
            'accepted' => ['Buyurtma tasdiqlandi', "{$code} buyurtmangiz qabul qilindi, tez orada tayyorlanadi."],
            'preparing' => ['Buyurtma tayyorlanmoqda', "{$code} buyurtmangiz hozir tayyorlanmoqda."],
            'ready' => ['Buyurtma tayyor', $delivery ? "{$code} buyurtmangiz tayyor, kuryer olib chiqadi." : "{$code} buyurtmangiz tayyor, olib ketishingiz mumkin."],
            'courier_assigned' => ['Kuryer tayinlandi', "{$code} buyurtmangizni kuryer olib chiqadi."],
            'delivery', 'on_the_way' => ["Buyurtma yo'lga chiqdi", "{$code} buyurtmangiz yo'lda."],
            'delivered' => $delivery
                ? ['Buyurtma yetkazildi', "{$code} buyurtmangiz yetkazildi. Yoqimli ishtaha!"]
                : ['Buyurtma tayyor', "{$code} buyurtmangiz tayyor, olib ketishingiz mumkin."],
            'canceled', 'rejected' => ['Buyurtma bekor qilindi', "{$code} buyurtmangiz bekor qilindi."],
            'refunded' => ['Pul qaytarildi', "{$code} buyurtmangiz bo'yicha pul qaytarilmoqda."],
            default => ["Buyurtma holati o'zgardi", "{$code} buyurtmangiz holati yangilandi."],
        };

        return new PushContent($title, $body, $this->data($order, 'order_updates'), $this->collapseKey($order));
    }

    /**
     * A new order for the store's staff.
     */
    public function forNewOrder(Order $order): PushContent
    {
        $kind = $order->type === 'delivery' ? 'yetkazib berish' : 'olib ketish';
        $total = number_format((float) $order->total_price, 0, '.', ' ');

        return new PushContent(
            'Yangi buyurtma',
            "#{$order->short_code} - {$kind}, {$total} UZS. Ilovaga kirib qabul qiling.",
            $this->data($order, 'staff_new_order'),
            $this->collapseKey($order),
        );
    }

    /**
     * An order the courier has to take (or that waits for one).
     */
    public function forCourier(Order $order, bool $assigned): PushContent
    {
        return new PushContent(
            $assigned ? 'Buyurtma sizga biriktirildi' : 'Buyurtma tayyor',
            $assigned
                ? "#{$order->short_code} buyurtmani qabul qiling va yo'lga chiqing."
                : "#{$order->short_code} buyurtma tayyor, kuryer biriktirilmagan.",
            $this->data($order, 'courier_tasks'),
            $this->collapseKey($order),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function data(Order $order, string $type): array
    {
        return [
            'type' => $type,
            'order_uuid' => (string) $order->uuid,
            'status' => $this->status($order),
        ];
    }

    protected function collapseKey(Order $order): string
    {
        return "order-{$order->uuid}";
    }

    protected function status(Order $order): string
    {
        return (string) ($order->status->value ?? $order->status);
    }
}
