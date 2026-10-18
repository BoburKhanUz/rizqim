<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Actions\Order\CreateOrderAction;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateOrderAction $createOrderAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;

        if ($userRole === 'courier') {
            $orders = \App\Models\Order::where('store_id', $user->store_id)
                ->where('branch_id', $user->branch_id)
                ->where(function($q) use ($user) {
                    $q->where('courier_id', $user->id)
                      ->orWhereNull('courier_id'); // See available orders to pickup
                })
                ->with(['items.variant.product', 'user', 'branch'])
                ->latest()
                ->get();
        } elseif ($userRole === 'admin' || $userRole === 'owner') {
            $orders = \App\Models\Order::where('store_id', $user->store_id)
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories'])
                ->latest()
                ->get();
        } elseif ($userRole === 'manager') {
            $orders = \App\Models\Order::where('store_id', $user->store_id)
                ->where('branch_id', $user->branch_id)
                ->with(['items.variant.product.category', 'user', 'courier', 'manager', 'branch', 'statusHistories'])
                ->latest()
                ->get();
        } else {
            $orders = $user->orders()
                ->with(['items.variant.product'])
                ->latest()
                ->get();
        }

        return $this->success($orders);
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        if ($userRole !== 'courier') {
            return $this->error('Unauthorized.', [], 403);
        }

        $order = \App\Models\Order::where('uuid', $uuid)
            ->where('courier_id', $user->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found or not assigned to you.', [], 404);
        }

        $oldStatus = $order->status->value ?? $order->status;
        $order->update([
            'status' => \App\Enums\OrderStatus::DELIVERED->value,
        ]);

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => \App\Enums\OrderStatus::DELIVERED->value,
            'note' => "Order completed/delivered by Courier: {$user->name}",
        ]);

        \App\Models\OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus,
            'to_status' => \App\Enums\OrderStatus::DELIVERED->value,
            'user_id' => $user->id,
            'changed_at' => now(),
        ]);

        $this->sendTelegramNotification($order, 'delivered');

        if ($oldStatus !== \App\Enums\OrderStatus::DELIVERED->value) {
            try {
                app(\App\Services\Finance\LedgerService::class)->processOrderDelivered($order, $user->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to process financials for order {$order->uuid}: " . $e->getMessage());
            }
        }

        event(new \App\Events\OrderStatusUpdated($order));

        return $this->success($order, 'Order delivered successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'type' => 'required|string|in:delivery,pickup,dine_in',
            'payment_method' => 'required|string',
            'branch_id' => 'required_if:type,pickup|nullable|exists:branches,id',
            'address' => 'required_if:type,delivery|nullable|string',
            'latitude' => 'required_if:type,delivery|nullable|numeric',
            'longitude' => 'required_if:type,delivery|nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->createOrderAction->execute(
                $request->user(),
                $request->items,
                $request->only(['store_id', 'type', 'payment_method', 'address', 'latitude', 'longitude', 'branch_id', 'notes', 'discount'])
            );

            // Trigger notification here eventually (e.g. Telegram Admin Notification)
            \App\Models\OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status->value ?? $order->status,
                'user_id' => $request->user()?->id,
                'changed_at' => now(),
            ]);

            try {
                $managers = \App\Models\User::where('store_id', $order->store_id)
                    ->where('role', \App\Enums\UserRole::MANAGER->value)
                    ->where('branch_id', $order->branch_id)
                    ->get();
                
                $botToken = config('services.telegram.bot_token');
                if ($botToken) {
                    foreach ($managers as $manager) {
                        if ($manager->telegram_id) {
                            $typeText = $order->type === 'delivery' ? 'ETKAZIB BERISH' : 'OLIB KETISH';
                            $text = "🔔 *Yangi buyurtma keldi!*\n\n"
                                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                  . "📍 *Yetkazish turi:* {$typeText}\n"
                                  . "💰 *Jami Summa:* " . number_format($order->total_price, 0, '.', ' ') . " UZS\n\n"
                                  . "Ilovaga kirib qabul qiling.";
                            $this->triggerTelegramSend($botToken, $manager->telegram_id, $text, $order->id, $manager->id, 'telegram_manager_new_order');
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to notify managers: " . $e->getMessage());
            }

            $paymentUrl = null;
            if (in_array($order->payment_method, ['click', 'payme'])) {
                $paymentUrl = $this->generatePaymentUrl($order);
            }

            return $this->success([
                'order_uuid' => $order->uuid,
                'status' => $order->status->value,
                'payment_url' => $paymentUrl,
            ], 'Order created successfully.', 201);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 400);
        }
    }

    /**
     * Generate actual Uzbek payment provider billing URLs dynamically from store config.
     */
    protected function generatePaymentUrl($order): string
    {
        $amount = (float) $order->total_price;
        $returnUrl = url('/admin'); // Redirect back to store after payment
        
        $settings = $order->store?->payment_settings ?? [];

        if ($order->payment_method === 'click') {
            $clickConfig = $settings['click'] ?? [];
            if (isset($clickConfig['enabled']) && !$clickConfig['enabled']) {
                return ''; // Disabled by store merchant
            }

            $serviceId = $clickConfig['service_id'] ?? env('CLICK_SERVICE_ID', '32104');
            $merchantId = $clickConfig['merchant_id'] ?? env('CLICK_MERCHANT_ID', '24185');
            
            return "https://my.click.uz/services/pay" .
                   "?service_id={$serviceId}" .
                   "&merchant_id={$merchantId}" .
                   "&amount={$amount}" .
                   "&transaction_param={$order->uuid}" .
                   "&return_url=" . urlencode($returnUrl);
        }

        if ($order->payment_method === 'payme') {
            $paymeConfig = $settings['payme'] ?? [];
            if (isset($paymeConfig['enabled']) && !$paymeConfig['enabled']) {
                return ''; // Disabled by store merchant
            }

            $merchantId = $paymeConfig['merchant_id'] ?? env('PAYME_MERCHANT_ID', '65eb2b988f01b97b0a7c4912');
            // Payme expects amount in tiyin (1 UZS = 100 tiyin)
            $tiyinAmount = $amount * 100;
            $params = "m={$merchantId};ac.order_id={$order->uuid};a={$tiyinAmount};c=" . urlencode($returnUrl);
            
            return "https://checkout.paycom.uz/" . base64_encode($params);
        }

        if ($order->payment_method === 'paynet') {
            $paynetConfig = $settings['paynet'] ?? [];
            if (isset($paynetConfig['enabled']) && !$paynetConfig['enabled']) {
                return '';
            }

            $serviceId = $paynetConfig['service_id'] ?? '12345';
            $merchantId = $paynetConfig['merchant_id'] ?? '67890';
            
            return "https://my.paynet.uz/service/pay" .
                   "?service_id={$serviceId}" .
                   "&merchant_id={$merchantId}" .
                   "&amount={$amount}" .
                   "&transaction_param={$order->uuid}" .
                   "&return_url=" . urlencode($returnUrl);
        }

        if ($order->payment_method === 'uzum') {
            $uzumConfig = $settings['uzum'] ?? [];
            if (isset($uzumConfig['enabled']) && !$uzumConfig['enabled']) {
                return '';
            }

            $merchantId = $uzumConfig['merchant_id'] ?? '123456';
            
            return "https://checkout.uzum.uz/pay" .
                   "?merchant_id={$merchantId}" .
                   "&amount={$amount}" .
                   "&order_id={$order->uuid}" .
                   "&return_url=" . urlencode($returnUrl);
        }

        return '';
    }

    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        if (!in_array($userRole, ['owner', 'manager', 'courier'])) {
            return $this->error('Unauthorized.', [], 403);
        }

        $request->validate([
            'status' => 'required|string|in:accepted,preparing,ready,courier_assigned,on_the_way,delivery,delivered,canceled,rejected',
            'courier_id' => 'nullable|integer',
        ]);

        $query = \App\Models\Order::where('uuid', $uuid)
            ->where('store_id', $user->store_id);

        if (in_array($userRole, ['manager', 'courier']) && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $order = $query->first();

        if (!$order) {
            return $this->error('Order not found or unauthorized branch.', [], 404);
        }

        $oldStatus = $order->status->value ?? $order->status;

        // Prevent canceling/rejecting if it is already past the new stage
        if (in_array($request->status, ['canceled', 'rejected'])) {
            if ($oldStatus !== 'new') {
                return $this->error('Buyurtma qabul qilinganidan keyin uni bekor qilib bo\'lmaydi.', [], 422);
            }
        }

        $updateData = [
            'status' => $request->status,
        ];

        if ($request->has('courier_id')) {
            $updateData['courier_id'] = $request->courier_id;
        }

        // Auto-assign courier/owner if we are moving to delivery/courier_assigned but no courier is assigned
        if (in_array($request->status, ['delivery', 'on_the_way', 'courier_assigned']) && !$order->courier_id && !isset($updateData['courier_id'])) {
            $courier = \App\Models\User::where('store_id', $order->store_id)
                ->where('role', \App\Enums\UserRole::COURIER->value)
                ->where('branch_id', $order->branch_id)
                ->first();
                
            if (!$courier) {
                $courier = \App\Models\User::where('store_id', $order->store_id)
                    ->where('role', \App\Enums\UserRole::COURIER->value)
                    ->first();
            }

            if ($courier) {
                $updateData['courier_id'] = $courier->id;
            } else {
                // Owner acts as fallback courier
                $owner = \App\Models\User::where('store_id', $order->store_id)
                    ->where('role', \App\Enums\UserRole::OWNER->value)
                    ->first();
                if ($owner) {
                    $updateData['courier_id'] = $owner->id;
                }
            }

            // Create assignment log
            if (isset($updateData['courier_id'])) {
                \App\Models\OrderAssignment::create([
                    'order_id' => $order->id,
                    'courier_id' => $updateData['courier_id'],
                    'assigned_by' => $user->id,
                    'assigned_branch_id' => $order->branch_id,
                    'is_current' => true,
                    'assigned_at' => now(),
                ]);
            }
        }

        $order->update($updateData);

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => $request->status,
            'note' => "Order status updated via Manager App to: {$request->status} by {$user->name}",
        ]);

        \App\Models\OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus,
            'to_status' => $request->status,
            'user_id' => $user->id,
            'changed_at' => now(),
        ]);

        $this->sendTelegramNotification($order, $request->status);

        if ($oldStatus !== \App\Enums\OrderStatus::DELIVERED->value && $request->status === \App\Enums\OrderStatus::DELIVERED->value) {
            try {
                app(\App\Services\Finance\LedgerService::class)->processOrderDelivered($order, $user->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to process financials for order {$order->uuid}: " . $e->getMessage());
            }
        }

        // Trigger notifications for status change dynamically to both customer and courier via Event
        if ($request->status === 'canceled' || $request->status === 'rejected') {
            event(new \App\Events\OrderCancelled($order));
        } else {
            event(new \App\Events\OrderStatusUpdated($order));
        }

        return $this->success($order->load(['items.variant.product', 'user', 'courier']), 'Order status updated successfully.');
    }

    public function couriers(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        
        // Fetch all active employees of the store (couriers, managers, and owners)
        $query = \App\Models\User::where('store_id', $user->store_id)
            ->whereIn('role', [
                \App\Enums\UserRole::COURIER->value,
                \App\Enums\UserRole::MANAGER->value,
                \App\Enums\UserRole::OWNER->value
            ]);

        if ($userRole === 'manager' && $user->branch_id) {
            $query->where(function($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                  ->orWhereNull('branch_id')
                  ->orWhere('role', \App\Enums\UserRole::OWNER->value);
            });
        } elseif ($request->has('branch_id')) {
            $query->where(function($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                  ->orWhereNull('branch_id')
                  ->orWhere('role', \App\Enums\UserRole::OWNER->value);
            });
        }

        $users = $query->get(['id', 'name', 'phone', 'telegram_username', 'role', 'branch_id']);

        $formatted = $users->map(function($u) {
            $roleLabel = match($u->role->value ?? $u->role) {
                'owner' => 'Ega / Owner',
                'manager' => 'Menejer',
                default => 'Kuryer'
            };
            return [
                'id' => $u->id,
                'name' => "{$u->name} ({$roleLabel})",
                'phone' => $u->phone,
                'telegram_username' => $u->telegram_username,
                'branch_id' => $u->branch_id
            ];
        });

        return $this->success($formatted);
    }

    public function assignCourier(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        if (!in_array($userRole, ['owner', 'manager'])) {
            return $this->error('Unauthorized.', [], 403);
        }

        $request->validate([
            'courier_id' => 'required|exists:users,id',
        ]);

        $query = \App\Models\Order::where('uuid', $uuid)
            ->where('store_id', $user->store_id);

        if ($userRole === 'manager' && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $order = $query->first();

        if (!$order) {
            return $this->error('Order not found or unauthorized branch.', [], 404);
        }

        // Verify courier/employee belongs to store (and same branch if manager)
        $courierQuery = \App\Models\User::where('store_id', $user->store_id)
            ->whereIn('role', [
                \App\Enums\UserRole::COURIER->value,
                \App\Enums\UserRole::MANAGER->value,
                \App\Enums\UserRole::OWNER->value
            ])
            ->where('id', $request->courier_id);

        if ($userRole === 'manager' && $user->branch_id) {
            $courierQuery->where(function($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                  ->orWhereNull('branch_id')
                  ->orWhere('role', \App\Enums\UserRole::OWNER->value);
            });
        }

        $courier = $courierQuery->first();

        if (!$courier) {
            return $this->error('Courier not found or not in this branch.', [], 422);
        }

        $oldStatus = $order->status->value ?? $order->status;
        $newStatus = \App\Enums\OrderStatus::COURIER_ASSIGNED->value;

        // Deactivate existing assignments
        \App\Models\OrderAssignment::where('order_id', $order->id)
            ->update(['is_current' => false]);

        // Create new assignment
        \App\Models\OrderAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'assigned_by' => $user->id,
            'assigned_branch_id' => $order->branch_id,
            'is_current' => true,
            'assigned_at' => now(),
        ]);

        // Update order
        $order->update([
            'courier_id' => $courier->id,
            'status' => $newStatus,
        ]);

        // Status histories
        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => $newStatus,
            'note' => "Courier assigned: {$courier->name} by {$user->name}",
        ]);

        \App\Models\OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'user_id' => $user->id,
            'changed_at' => now(),
        ]);

        // Send targeted Telegram notification to this Courier
        try {
            $botToken = config('services.telegram.bot_token');
            if ($botToken && $courier->telegram_id) {
                list($displayAddress, $keyboard) = $this->getAddressAndKeyboard($order->delivery_address, $order->shipping_address);

                $text = "🚚 *Yangi buyurtma biriktirildi!*\n\n"
                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                      . "📍 *Manzil:* {$displayAddress}\n"
                      . "💰 *Jami summa:* " . number_format($order->total_price, 0, '.', ' ') . " UZS\n\n"
                      . "Ilovadan buyurtmani qabul qiling va yo'lga chiqing! 🚀";

                $this->triggerTelegramSend($botToken, $courier->telegram_id, $text, $order->id, $courier->id, 'telegram_courier_assigned', $keyboard);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send telegram notification to courier {$courier->id}: " . $e->getMessage());
        }

        event(new \App\Events\OrderStatusUpdated($order));

        return $this->success($order->load(['items.variant.product', 'user', 'courier']), 'Courier assigned successfully.');
    }

    protected function sendTelegramNotification($order, string $status): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            if (!$botToken) {
                return;
            }

            // 1. ACCEPTED -> notify customer
            if ($status === 'accepted') {
                if ($order->user?->telegram_id) {
                    $text = "✅ *Sizning buyurtmangiz qabul qilindi!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "⏳ Tez orada tayyor bo'ladi.";
                    $this->triggerTelegramSend($botToken, $order->user->telegram_id, $text, $order->id, $order->user->id, 'telegram_customer_accepted');
                }
            }

            // 2. READY -> notify branch manager OR courier
            if ($status === 'ready') {
                list($displayAddress, $keyboard) = $this->getAddressAndKeyboard($order->delivery_address, $order->shipping_address);

                // If a courier is assigned, notify that specific courier
                if ($order->courier_id && $order->courier?->telegram_id) {
                    $text = "📦 *Buyurtma tayyor!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "📍 *Manzil:* {$displayAddress}\n"
                          . "Yetkazib berishni boshlashingiz mumkin.";
                    $this->triggerTelegramSend($botToken, $order->courier->telegram_id, $text, $order->id, $order->courier_id, 'telegram_courier_ready', $keyboard);
                } else {
                    // Otherwise notify all couriers in this branch
                    $couriers = \App\Models\User::where('store_id', $order->store_id)
                        ->where('role', \App\Enums\UserRole::COURIER->value)
                        ->where('branch_id', $order->branch_id)
                        ->get();
                    if ($couriers->isNotEmpty()) {
                        foreach ($couriers as $courier) {
                            if ($courier->telegram_id) {
                                $text = "🔔 *Yangi tayyor buyurtma! (Kuryer biriktirilmagan)*\n\n"
                                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                      . "📍 *Manzil:* {$displayAddress}\n"
                                      . "Menejer orqali o'zingizni biriktirishingizni so'rang.";
                                $this->triggerTelegramSend($botToken, $courier->telegram_id, $text, $order->id, $courier->id, 'telegram_branch_couriers_ready', $keyboard);
                            }
                        }
                    } else {
                        // Fallback: if no couriers are registered, notify managers/owners as they act as couriers
                        $managers = \App\Models\User::where('store_id', $order->store_id)
                            ->where('role', \App\Enums\UserRole::MANAGER->value)
                            ->whereNotNull('telegram_id')
                            ->get();
                            
                        if ($managers->isNotEmpty()) {
                            foreach ($managers as $manager) {
                                $text = "📦 *Buyurtma tayyor! (Kuryer yo'qligi sababli sizga yuborildi)*\n\n"
                                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                      . "📍 *Manzil:* {$displayAddress}\n"
                                      . "Yetkazib berishni boshlashingiz mumkin.";
                                $this->triggerTelegramSend($botToken, $manager->telegram_id, $text, $order->id, $manager->id, 'telegram_courier_ready', $keyboard);
                            }
                        } else {
                            $owners = \App\Models\User::where('store_id', $order->store_id)
                                ->where('role', \App\Enums\UserRole::OWNER->value)
                                ->whereNotNull('telegram_id')
                                ->get();
                            foreach ($owners as $owner) {
                                $text = "📦 *Buyurtma tayyor! (Kuryer yo'qligi sababli sizga yuborildi)*\n\n"
                                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                      . "📍 *Manzil:* {$displayAddress}\n"
                                      . "Yetkazib berishni boshlashingiz mumkin.";
                                $this->triggerTelegramSend($botToken, $owner->telegram_id, $text, $order->id, $owner->id, 'telegram_courier_ready', $keyboard);
                            }
                        }
                    }
                }
            }

            // 3. ON_THE_WAY -> notify customer
            if ($status === 'delivery' || $status === 'on_the_way') {
                if ($order->user?->telegram_id) {
                    $courierName = $order->courier?->name ?? 'Kuryer';
                    $courierPhone = $order->courier?->phone ?? '';
                    $text = "🚚 *Buyurtmangiz yo\'lda!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "👤 *Kuryer:* {$courierName} " . ($courierPhone ? "({$courierPhone})" : "") . "\n"
                          . "Tez orada yetib boradi.";
                    $this->triggerTelegramSend($botToken, $order->user->telegram_id, $text, $order->id, $order->user->id, 'telegram_customer_on_the_way');
                }
            }

            // 4. DELIVERED -> notify customer
            if ($status === 'delivered') {
                if ($order->user?->telegram_id) {
                    $text = "🎉 *Buyurtmangiz yetkazib berildi!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "Sog\' bo\'ling, yana buyurtma kutib qolamiz! 😊";
                    $this->triggerTelegramSend($botToken, $order->user->telegram_id, $text, $order->id, $order->user->id, 'telegram_customer_delivered');
                }
            }

            // 5. CANCELLED -> notify customer
            if ($status === 'canceled' || $status === 'rejected') {
                if ($order->user?->telegram_id) {
                    $text = "❌ *Sizning buyurtmangiz bekor qilindi.*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}";
                    $this->triggerTelegramSend($botToken, $order->user->telegram_id, $text, $order->id, $order->user->id, 'telegram_customer_canceled');
                }
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send telegram notification flow: " . $e->getMessage());
        }
    }

    protected function triggerTelegramSend(string $botToken, string $chatId, string $text, int $orderId, int $userId, string $type, array $replyMarkup = []): void
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if (!empty($replyMarkup)) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

            \App\Models\NotificationLog::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => $type,
                'status' => $response->successful() ? 'sent' : 'failed',
                'sent_at' => now(),
                'error_message' => $response->successful() ? null : $response->body(),
            ]);
        } catch (\Exception $e) {
            \App\Models\NotificationLog::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => $type,
                'status' => 'failed',
                'sent_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function exportReport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        $store = \App\Models\Store::find($user->store_id);
        if (!in_array($userRole, ['admin', 'manager'])) {
            return $this->error('Unauthorized.', [], 403);
        }

        $request->validate([
            'timeframe' => 'required|string|in:today,weekly,monthly,yearly,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $timeframe = $request->timeframe;
        $query = \App\Models\Order::where('store_id', $user->store_id)
            ->with(['items.variant.product.category', 'user', 'courier']);

        if ($timeframe === 'today') {
            $query->whereDate('updated_at', today());
        } elseif ($timeframe === 'weekly') {
            $query->where('updated_at', '>=', now()->subDays(7));
        } elseif ($timeframe === 'monthly') {
            $query->where('updated_at', '>=', now()->subDays(30));
        } elseif ($timeframe === 'yearly') {
            $query->where('updated_at', '>=', now()->subDays(365));
        } elseif ($timeframe === 'custom' && $request->start_date && $request->end_date) {
            $start = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $end = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('updated_at', [$start, $end]);
        }

        $orders = $query->latest()->get();

        $deliveredOrders = $orders->filter(fn($o) => ($o->status->value ?? $o->status) === 'delivered');
        $canceledOrders = $orders->filter(fn($o) => ($o->status->value ?? $o->status) === 'canceled');

        $totalRevenue = $deliveredOrders->reduce(fn($sum, $o) => $sum + (float) $o->total_price, 0);

        // Generate CSV in memory with BOM for proper Excel Uzbek/Russian UTF-8 support
        $tempFile = fopen('php://temp', 'r+');
        fprintf($tempFile, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        $categories = \App\Models\Category::all();

        // Header columns starting with base columns
        $headers = [
            'Buyurtma ID',
            'Mijoz nomi',
            'Telefon',
            'Sana'
        ];

        // Dynamically add a column for each category
        foreach ($categories as $category) {
            $headers[] = $category->name . " (Mahsulotlar)";
        }

        // Base tail columns
        $headers[] = 'Yetkazish xizmati (UZS)';
        $headers[] = 'Jami Summa (UZS)';
        $headers[] = 'To\'lov Usuli';
        $headers[] = 'Kuryer';
        $headers[] = 'Status';

        fputcsv($tempFile, $headers);

        foreach ($orders as $order) {
            $row = [
                "#" . $order->short_code,
                $order->user?->name ?? 'Mijoz',
                $order->user?->phone ?? '-',
                $order->created_at->format('Y-m-d H:i')
            ];

            // For each category, collect the items of the order belonging to it
            foreach ($categories as $category) {
                $categoryItems = [];
                foreach ($order->items as $item) {
                    // Fetch category with name-based fallback
                    $itemCategoryName = $item->variant?->product?->category?->name;
                    if (!$itemCategoryName) {
                        $itemCategoryName = (stripos($item->product_name, 'cola') !== false || 
                                             stripos($item->product_name, 'water') !== false || 
                                             stripos($item->product_name, 'fanta') !== false || 
                                             stripos($item->product_name, 'sprite') !== false || 
                                             stripos($item->product_name, 'pepsi') !== false || 
                                             stripos($item->product_name, 'suv') !== false || 
                                             stripos($item->product_name, 'choy') !== false) ? 'Drinks' : 'Burgers';
                    }

                    if (strtolower($itemCategoryName) === strtolower($category->name)) {
                        $variantSuffix = $item->variant_name ? " ({$item->variant_name})" : "";
                        $categoryItems[] = "{$item->quantity}x {$item->product_name}{$variantSuffix} (" . number_format($item->total_price, 0, '.', '') . " UZS)";
                    }
                }
                $row[] = implode("\n", $categoryItems);
            }

            // Append tail items
            $row[] = number_format($order->delivery_price ?? 0, 0, '.', '');
            $row[] = number_format($order->total_price, 0, '.', '');
            $row[] = strtoupper($order->payment_method ?? 'CASH');
            $row[] = $order->courier?->name ?? '-';
            $row[] = strtoupper($order->status->value ?? $order->status);

            fputcsv($tempFile, $row);
        }

        rewind($tempFile);
        $csvContent = stream_get_contents($tempFile);
        fclose($tempFile);

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return $this->error('Telegram bot not configured globally.', [], 400);
        }

        $chatId = $user->telegram_id ?: $store?->telegram_admin_chat_id;
        if (!$chatId) {
            return $this->error('Target Telegram Chat ID not found.', [], 400);
        }

        // Send to Telegram
        try {
            $timeframeLabel = $timeframe === 'custom' 
                ? $request->start_date . " dan " . $request->end_date . " gacha" 
                : strtoupper($timeframe);

            $caption = "📊 *" . ($store?->name ?? 'Restoran') . " - Hisoboti*\n\n"
                . "📅 *Davr:* {$timeframeLabel}\n"
                . "💰 *Jami tushum:* " . number_format($totalRevenue, 0, '.', ' ') . " UZS\n"
                . "✅ *Muvaffaqiyatli:* " . count($deliveredOrders) . " ta\n"
                . "❌ *Bekor qilingan:* " . count($canceledOrders) . " ta\n\n"
                . "📁 _Barcha ma'lumotlar XLSX (Excel) formatidagi jadvalda biriktirildi._";

            $response = \Illuminate\Support\Facades\Http::attach(
                'document',
                $csvContent,
                "report_{$timeframe}_" . date('Y_m_d') . ".csv"
            )->post("https://api.telegram.org/bot{$botToken}/sendDocument", [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'Markdown'
            ]);

            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::warning('Telegram sendDocument failed', ['res' => $response->json()]);
                return $this->error('Hisobotni Telegramga yuborib bo\'lmadi.', [], 500);
            }
        } catch (\Exception $e) {
            return $this->error('Xatolik yuz berdi: ' . $e->getMessage(), [], 500);
        }

        return $this->success(null, 'Hisobot Telegramingizga muvaffaqiyatli yuborildi! 🚀');
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $unreadCount = \App\Models\NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $paginator = \App\Models\NotificationLog::where('user_id', $user->id)
            ->with(['order'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        $items = collect($paginator->items())->map(function ($log) {
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
        });

        return $this->success([
            'items' => $items,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ]
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        \App\Models\NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->success(null, 'Barcha xabarnomalar o\'qildi deb belgilandi.');
    }

    protected function getAddressAndKeyboard(?string $addressText, ?array $shippingAddress = null): array
    {
        $displayAddress = $addressText ?: "Ko'rsatilmagan";
        $keyboard = [];

        $lat = $shippingAddress['latitude'] ?? null;
        $lng = $shippingAddress['longitude'] ?? null;

        if ($lat && $lng) {
            $url = "https://yandex.com/maps/?pt={$lng},{$lat}&z=16&l=map";
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                    ]
                ]
            ];
        } elseif ($addressText) {
            if (preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                $url = $matches[1];
                $cleanAddress = trim(str_replace(['📍 Lokatsiya:', 'Lokatsiya:', $url], '', $addressText));
                $cleanAddress = trim(preg_replace('/\s+/', ' ', $cleanAddress));
                $displayAddress = $cleanAddress ?: "GPS lokatsiya";
            } else {
                $url = "https://yandex.com/maps/?text=" . urlencode($addressText);
                $displayAddress = $addressText;
            }

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                    ]
                ]
            ];
        }

        return [$displayAddress, $keyboard];
    }
}
