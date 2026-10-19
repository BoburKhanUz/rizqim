<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\Payment;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\OrderType;
use App\Services\Promo\InvalidPromoCodeException;
use App\Services\Promo\PromoCodeService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class CreateOrderAction
{
    public function __construct(
        protected PromoCodeService $promoCodes
    ) {}

    /**
     * @param \App\Models\User $user
     * @param array $items Array of ['variant_id' => uuid, 'quantity' => int]
     * @param array $payload Additional checkout data (type, address, notes, payment_method, promo_code)
     */
    public function execute($user, array $items, array $payload): Order
    {
        // Nothing could ever mark such an order paid (no webhook for it yet)
        if (!\App\Models\Store::isPaymentMethodAvailable((string) ($payload['payment_method'] ?? 'cash'))) {
            throw ValidationException::withMessages(['payment_method' => "Bu to'lov usuli hozircha mavjud emas"]);
        }

        return DB::transaction(function () use ($user, $items, $payload) {
            
            $storeId = $payload['store_id'];
            $store = \App\Models\Store::findOrFail($storeId);

            // 1. Store Status & Working Hours Validation
            $storeStatus = $store->status->value ?? $store->status;
            if ($storeStatus === \App\Enums\StoreStatus::PAYMENT_REQUIRED->value) {
                throw new Exception("Restoran vaqtincha to'lov qabul qilmayapti (Qarz limitidan oshgan).");
            }
            if ($storeStatus === \App\Enums\StoreStatus::FINANCE_HOLD->value) {
                throw new Exception("Restoran faoliyati moliyaviy tekshiruv tufayli to'xtatilgan.");
            }
            if ($store->exceedsCreditLimit()) {
                throw new Exception("Restoran vaqtincha to'lov qabul qilmayapti (Meyordagi qarz limitidan oshgan).");
            }

            if (!$store->is_open) {
                throw new Exception("Uzr, ushbu restoran hozir yopiq yoki ish vaqtidan tashqari.");
            }

            // Payment Method Toggles
            $paymentMethod = $payload['payment_method'] ?? 'cash';
            $settings = $store->payment_settings ?? [];
            $acceptCash = (bool) ($settings['cash']['enabled'] ?? ($settings['cash'] ?? true));
            $acceptClick = (bool) ($settings['click']['enabled'] ?? ($settings['click'] ?? false));
            $acceptPayme = (bool) ($settings['payme']['enabled'] ?? ($settings['payme'] ?? false));
            $acceptUzum = (bool) ($settings['uzum']['enabled'] ?? ($settings['uzum'] ?? false));
            $acceptPaynet = (bool) ($settings['paynet']['enabled'] ?? ($settings['paynet'] ?? false));

            // Validate Order Type (Delivery / Pickup)
            $orderType = $payload['type'] ?? 'delivery';
            $deliveryEnabled = (bool) ($settings['delivery_enabled'] ?? true);
            $pickupEnabled = (bool) ($settings['pickup_enabled'] ?? true);

            if ($orderType === 'delivery' && !$deliveryEnabled) {
                throw new Exception("Ushbu restoranda yetkazib berish xizmati vaqtincha to'xtatilgan.");
            }
            if ($orderType === 'pickup' && !$pickupEnabled) {
                throw new Exception("Ushbu restoranda olib ketish (takeaway) xizmati vaqtincha to'xtatilgan.");
            }

            if ($paymentMethod === 'cash') {
                if (!$acceptCash) {
                    throw new Exception("Restoran naqd to'lov qabul qilmaydi.");
                }

                // Check for recent uncollected/customer-canceled cash pickup orders
                if (Order::cashPickupBanApplies($user->id)) {
                    throw new Exception("Sizda olib ketish buyurtmasini bekor qilish holatlari aniqlanganligi sababli, naqd pulda buyurtma berish taqiqlangan. Iltimos, onlayn to'lov turlaridan foydalaning.");
                }
            }
            if ($paymentMethod === 'click' && !$acceptClick) {
                throw new Exception("Restoran Click orqali to'lov qabul qilmaydi.");
            }
            if ($paymentMethod === 'payme' && !$acceptPayme) {
                throw new Exception("Restoran Payme orqali to'lov qabul qilmaydi.");
            }
            if ($paymentMethod === 'uzum' && !$acceptUzum) {
                throw new Exception("Restoran Uzum Bank orqali to'lov qabul qilmaydi.");
            }
            if ($paymentMethod === 'paynet' && !$acceptPaynet) {
                throw new Exception("Restoran Paynet orqali to'lov qabul qilmaydi.");
            }

            // 2. Branch Routing Engine
            $branch = null;
            $customerAddress = $payload['address'] ?? null;
            
            if ($payload['type'] === 'pickup') {
                $branch = \App\Models\Branch::where('id', $payload['branch_id'])
                    ->where('store_id', $storeId)
                    ->where('status', \App\Enums\BranchStatus::ACTIVE->value)
                    ->first();
                if (!$branch) {
                    throw new Exception("Tanlangan filial ushbu restoranga tegishli emas yoki hozir ishlamayapti.");
                }
                $customerAddress = $branch->address ?? $store->address ?? 'Restoran filiali';
            } elseif ($payload['type'] === 'delivery') {
                $lat = $payload['latitude'] ?? null;
                $lng = $payload['longitude'] ?? null;
                
                $branches = \App\Models\Branch::where('store_id', $storeId)
                    ->where('status', \App\Enums\BranchStatus::ACTIVE->value)
                    ->get();
                    
                if ($branches->isEmpty()) {
                    throw new Exception("Ushbu restoranning faol filiallari mavjud emas.");
                }

                $closestBranch = null;

                if ($lat && $lng) {
                    $minDistance = INF;
                    foreach ($branches as $b) {
                        if (!$b->latitude || !$b->longitude || !$b->radius_km) continue;
                        
                        $distance = $this->calculateDistance($lat, $lng, $b->latitude, $b->longitude);
                        if ($distance <= $b->radius_km && $distance < $minDistance) {
                            $minDistance = $distance;
                            $closestBranch = $b;
                        }
                    }
                }

                // Fallback to the first branch only if coordinates are missing, otherwise throw exception
                if (!$closestBranch) {
                    if ($lat && $lng) {
                        throw new Exception("Uzr, siz kiritgan manzil ushbu restoranning yetkazib berish hududidan tashqarida joylashgan.");
                    }
                    $closestBranch = $branches->first();
                }

                $branch = $closestBranch;
            }

            // 3. Validate variants and calculate total securely
            $totalAmount = Money::zero();
            $orderItemsData = [];
            
            // The same variant may be sent on several lines; stock is checked against the combined quantity
            $requestedQuantities = [];
            foreach ($items as $item) {
                $uuid = strtolower((string) $item['variant_id']);
                $requestedQuantities[$uuid] = ($requestedQuantities[$uuid] ?? 0) + (int) $item['quantity'];
            }

            // Fetch variants securely by uuid for the active store, locked in id order so concurrent orders can't oversell or deadlock
            $variants = ProductVariant::with('product')
                ->whereIn('uuid', array_keys($requestedQuantities))
                ->where('is_active', true)
                ->whereHas('product', function($q) use ($storeId) {
                    $q->where('store_id', $storeId)->where('status', 'active');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($variant) => strtolower($variant->uuid));

            foreach ($requestedQuantities as $uuid => $quantity) {
                $variant = $variants->get($uuid);
                
                if (!$variant) {
                    throw new Exception("Product variant not found or unavailable.");
                }

                // Check branch product variant availability override
                if ($branch) {
                    $branchOverride = \App\Models\BranchProductVariant::where('branch_id', $branch->id)
                        ->where('variant_id', $variant->id)
                        ->first();

                    if ($branchOverride && !$branchOverride->is_active) {
                        $reason = $branchOverride->hidden_reason ? " ({$branchOverride->hidden_reason})" : "";
                        throw new Exception("Uzr, '{$variant->product->name} ({$variant->name})' mahsuloti ushbu filialda vaqtincha mavjud emas{$reason}.");
                    }
                }
                
                if ($variant->stock < $quantity) {
                    throw $this->outOfStock($variant, (int) $variant->stock);
                }
                
                $price = Money::of($variant->price);
                $lineTotal = $price->times($quantity);
                
                $totalAmount = $totalAmount->plus($lineTotal);
                
                // Prepare snapshot data
                $orderItemsData[] = [
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'quantity' => $quantity,
                    'unit_price' => $price->toString(),
                    'total_price' => $lineTotal->toString(),
                    'variant_model' => $variant, // Temporary reference for stock deduction
                ];
            }
            
            if (!$totalAmount->isPositive()) {
                throw new Exception("Buyurtma summasi noldan katta bo'lishi kerak.");
            }

            // 4. Minimum Order Validation
            $minOrderAmount = Money::of($store->min_order_amount);
            if ($minOrderAmount->isPositive() && $totalAmount->isLessThan($minOrderAmount)) {
                throw new Exception("Eng kam buyurtma summasi: " . number_format($minOrderAmount->toFloat(), 0, '.', ' ') . " UZS.");
            }

            // 5. Radius validation is now handled during Branch Routing Engine (above)
            // Fetch delivery fee from store payment settings
            $deliveryFee = Money::zero();
            $configuredFee = $store->payment_settings['delivery_fee'] ?? 0;
            if ($payload['type'] === 'delivery' && is_numeric($configuredFee)) {
                $deliveryFee = Money::of($configuredFee);
            }

            // 2. Create Order
            $shortCode = 'ORD-' . strtoupper(\Illuminate\Support\Str::random(6));
            
            // Determine payment owner: PLATFORM or STORE
            $paymentOwner = 'STORE';
            if ($paymentMethod === 'keshbek') {
                $paymentOwner = 'PLATFORM';
            } elseif ($paymentMethod !== 'cash' && !$store->collectsOwnPayments($paymentMethod)) {
                // Platform accounts, and own accounts without complete merchant credentials for this provider, are paid to
                // the platform (URL + webhook key) and settled to the store's wallet, so the store never loses the money.
                $paymentOwner = 'PLATFORM';
            }

            // Discount is computed only on the server, from a promo code locked for the rest of the transaction
            $discount = Money::zero();
            $promoCodeId = null;
            $promoCode = trim((string) ($payload['promo_code'] ?? ''));
            if ($promoCode !== '') {
                try {
                    $promo = $this->promoCodes->find($promoCode, true);
                    $discount = $this->promoCodes->discountFor($promo, (int) $storeId, $totalAmount, $user->id);
                } catch (InvalidPromoCodeException $e) {
                    throw ValidationException::withMessages(['promo_code' => $e->getMessage()]);
                }
                $promoCodeId = $promo->id;
            }

            $totalPrice = Money::max(Money::zero(), $totalAmount->minus($discount)->plus($deliveryFee));

            // Same base and rounding the ledger settles on, so the stored snapshot is exactly what gets charged
            $commissionAmount = $totalPrice->percent($store->commission_rate ?? 10);

            $paymentStatus = PaymentStatus::PENDING;
            if ($paymentMethod === 'keshbek') {
                // Re-read under lock: the authenticated user instance may hold a stale balance
                $balance = Money::of(User::whereKey($user->id)->lockForUpdate()->value('balance'));
                $insufficientBalance = "Keshbek balansi yetarli emas (Mavjud: " . number_format($balance->toFloat(), 0, '.', ' ') . " ball, Kerak: " . number_format($totalPrice->toFloat(), 0, '.', ' ') . " UZS).";
                if ($balance->isLessThan($totalPrice)) {
                    throw new Exception($insufficientBalance);
                }
                $debited = User::whereKey($user->id)
                    ->where('balance', '>=', $totalPrice->toString())
                    ->decrement('balance', $totalPrice->toString());
                if ($debited !== 1) {
                    throw new Exception($insufficientBalance);
                }
                $paymentStatus = PaymentStatus::PAID;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $storeId,
                'branch_id' => $branch?->id,
                'short_code' => $shortCode,
                'total_price' => $totalPrice->toString(),
                'subtotal' => $totalAmount->toString(),
                'delivery_price' => $deliveryFee->toString(),
                'discount' => $discount->toString(),
                'promo_code_id' => $promoCodeId,
                'commission_amount' => $commissionAmount->toString(),
                'status' => OrderStatus::NEW,
                'order_type' => $payload['type'],
                'payment_method' => $paymentMethod,
                'payment_owner' => $paymentOwner,
                'payment_status' => $paymentStatus,
                'shipping_address' => [
                    'address' => $customerAddress,
                    'latitude' => $payload['type'] === 'pickup' ? ($branch->latitude ?? null) : ($payload['latitude'] ?? null),
                    'longitude' => $payload['type'] === 'pickup' ? ($branch->longitude ?? null) : ($payload['longitude'] ?? null),
                ],
                'notes' => $payload['notes'] ?? null,
            ]);

            // Create initial payment log for consistent transaction records
            Payment::create([
                'order_id' => $order->id,
                'provider' => $order->payment_method,
                'amount' => $totalPrice->toString(),
                'status' => $paymentStatus,
            ]);

            // 3. Create Order Items & Deduct Stock
            foreach ($orderItemsData as $data) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $data['product_variant_id'],
                    'product_name' => $data['product_name'],
                    'variant_name' => $data['variant_name'],
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'total_price' => $data['total_price'],
                ]);
                
                // Deduct stock
                $deducted = ProductVariant::whereKey($data['product_variant_id'])
                    ->where('stock', '>=', $data['quantity'])
                    ->decrement('stock', $data['quantity']);
                if ($deducted !== 1) {
                    throw $this->outOfStock($data['variant_model']);
                }
            }

            // 4. Create Status History
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => OrderStatus::NEW->value,
                'notes' => 'Order created via Mini App',
            ]);
            
            // After commit: a rolled-back order is never announced, nor its Telegram cards built under the row locks
            DB::afterCommit(fn () => event(new \App\Events\OrderCreated($order)));

            // Create notification logs for the customer
            \App\Models\NotificationLog::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'new_order',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Create notification logs for store owners and managers
            $storeUsers = \App\Models\User::where('store_id', $storeId)
                ->whereIn('role', ['owner', 'manager'])
                ->get();
            foreach ($storeUsers as $su) {
                \App\Models\NotificationLog::create([
                    'user_id' => $su->id,
                    'order_id' => $order->id,
                    'type' => 'new_order',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
 
            return $order;
        });
    }

    private function outOfStock(ProductVariant $variant, ?int $available = null): ValidationException
    {
        $name = $variant->product->name . ($variant->name ? " ({$variant->name})" : '');
        $availableText = $available !== null ? " (mavjud: " . max(0, $available) . " ta)" : '';

        return ValidationException::withMessages([
            'items' => "Uzr, '{$name}' mahsulotidan yetarli miqdorda qolmagan{$availableText}. Iltimos, savatni yangilang.",
        ]);
    }

    /**
     * Calculate distance between two coordinates in kilometers using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}
