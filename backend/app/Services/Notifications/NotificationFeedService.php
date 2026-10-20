<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\User;

/**
 * The in-app notification feed: a user's NotificationLog rows, newest first, titled by their type.
 */
class NotificationFeedService
{
    public function page(User $user): array
    {
        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $paginator = NotificationLog::where('user_id', $user->id)
            ->with(['order'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        $items = collect($paginator->items())->map(fn (NotificationLog $log) => $this->item($log));

        return [
            'items' => $items,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ]
        ];
    }

    public function markAllRead(User $user): void
    {
        NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    protected function item(NotificationLog $log): array
    {
        $title = 'Xabar';
        $message = 'Tizim xabarnomasi';

        // Normalize type by removing telegram prefix helper strings
        $normalizedType = str_replace(
            ['telegram_customer_', 'telegram_courier_', 'telegram_branch_couriers_', 'telegram_branch_'],
            '',
            $log->type
        );

        if ($log->order) {
            $short = $log->order->short_code;

            if ($normalizedType === 'new_order') {
                $title = 'Yangi buyurtma 🛍';
                $message = "Yangi buyurtma qabul qilindi: #{$short}";
            } elseif ($normalizedType === 'accepted') {
                $title = 'Buyurtma qabul qilindi ✅';
                $message = "Buyurtmangiz #{$short} qabul qilindi.";
            } elseif ($normalizedType === 'preparing') {
                $title = 'Tayyorlanmoqda 🍳';
                $message = "Buyurtmangiz #{$short} tayyorlanmoqda.";
            } elseif ($normalizedType === 'ready') {
                $title = 'Tayyor bo\'ldi 📦';
                $message = "Buyurtmangiz #{$short} tayyor bo'ldi.";
            } elseif ($normalizedType === 'courier_assigned') {
                $title = 'Kuryer tayinlandi 🚚';
                $message = "Buyurtmangiz #{$short} uchun kuryer tayinlandi.";
            } elseif ($normalizedType === 'on_the_way') {
                $title = 'Yo\'lda 🚀';
                $message = "Buyurtmangiz #{$short} yo'lda.";
            } elseif ($normalizedType === 'delivered') {
                $title = 'Yetkazib berildi 🎉';
                $message = "Buyurtmangiz #{$short} muvaffaqiyatli yetkazildi. Yoqimli ishtaha!";
            } elseif ($normalizedType === 'canceled' || $normalizedType === 'rejected') {
                $title = 'Bekor qilindi ❌';
                $message = "Buyurtmangiz #{$short} bekor qilindi.";
            }
        } elseif ($normalizedType === 'complaint_reply') {
            $title = 'Shikoyatga javob ✍️';
            $message = "Sizning shikoyatingizga javob berildi.";
        } elseif ($normalizedType === 'store_payment_required') {
            $title = 'To\'lov Talab Qilinadi ⚠️';
            $message = "Do'konda qarz limiti tugaganligi sababli buyurtma olish to'xtatildi. Iltimos to'lov qiling.";
        }

        return [
            'id' => $log->id,
            'title' => $title,
            'message' => $message,
            'type' => $log->type,
            'status' => $log->status,
            'is_read' => (bool)$log->is_read,
            'read_at' => $log->read_at ? $log->read_at->toIso8601String() : null,
            'sent_at' => $log->sent_at ? $log->sent_at->toIso8601String() : now()->toIso8601String(),
        ];
    }
}
