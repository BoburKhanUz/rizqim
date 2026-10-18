<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\Payment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\OrderType;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateOrderAction
{
    /**
     * @param \App\Models\User $user
     * @param array $items Array of ['variant_id' => uuid, 'quantity' => int]
     * @param array $payload Additional checkout data (type, address, notes, payment_method)
     */
    public function execute($user, array $items, array $payload): Order
    {
        return DB::transaction(function () use ($user, $items, $payload) {
            
            $storeId = $payload['store_id'];
            $store = \App\Models\Store::findOrFail($storeId);

            // 1. Store Status & Working Hours Validation
            if ($store->status === \App\Enums\StoreStatus::PAYMENT_REQUIRED->value) {
                throw new Exception("Restoran vaqtincha to'lov qabul qilmayapti (Qarz limitidan oshgan).");
            }
            if ($store->status === \App\Enums\StoreStatus::FINANCE_HOLD->value) {
                throw new Exception("Restoran faoliyati moliyaviy tekshiruv tufayli to'xtatilgan.");
            }
            if ($store->balance >= abs($store->credit_limit)) {
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

                // Check for past uncollected/canceled cash pickup orders
                $hasFailedCashPickup = \App\Models\Order::where('user_id', $user->id)
                    ->where('order_type', 'pickup')
                    ->where('payment_method', 'cash')
                    ->whereIn('status', ['canceled', 'rejected'])
                    ->exists();
                if ($hasFailedCashPickup) {
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
            $totalAmount = 0;
            $orderItemsData = [];
            
            foreach ($items as $item) {
                // Fetch variant securely by uuid and ensure it belongs to the active store
                $variant = ProductVariant::with('product')
                    ->where('uuid', $item['variant_id'])
                    ->whereHas('product', function($q) use ($storeId) {
                        $q->where('store_id', $storeId)->where('status', 'active');
                    })
                    ->first();
                
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
                
                if ($variant->stock < $item['quantity']) {
                    throw new Exception("Not enough stock for {$variant->product->name}. Available: {$variant->stock}");
                }
                
                $price = $variant->price;
                $quantity = $item['quantity'];
                $totalPrice = $price * $quantity;
                
                $totalAmount += $totalPrice;
                
                // Prepare snapshot data
                $orderItemsData[] = [
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total_price' => $totalPrice,
                    'variant_model' => $variant, // Temporary reference for stock deduction
                ];
            }
            
            if ($totalAmount <= 0) {
                throw new Exception("Buyurtma summasi noldan katta bo'lishi kerak.");
            }

            // 4. Minimum Order Validation
            if ($store->min_order_amount > 0 && $totalAmount < $store->min_order_amount) {
                throw new Exception("Eng kam buyurtma summasi: " . number_format($store->min_order_amount, 0, '.', ' ') . " UZS.");
            }

            // 5. Radius validation is now handled during Branch Routing Engine (above)
            // Fetch delivery fee from store payment settings
            $deliveryFee = 0;
            if ($payload['type'] === 'delivery') {
                $deliveryFee = (float) ($store->payment_settings['delivery_fee'] ?? 0);
            }

            // Calculate marketplace commission based on store's commission_rate
            $commissionRate = $store->commission_rate ?? 10;
            $commissionAmount = ($totalAmount + $deliveryFee) * ($commissionRate / 100);

            // 2. Create Order
            $shortCode = 'ORD-' . strtoupper(\Illuminate\Support\Str::random(6));
            
            // Determine payment owner: PLATFORM or STORE
            $paymentOwner = 'STORE';
            if ($paymentMethod === 'keshbek') {
                $paymentOwner = 'PLATFORM';
            } elseif ($paymentMethod !== 'cash' && $store->payment_account_type === 'platform_account') {
                $paymentOwner = 'PLATFORM';
            }

            $discount = (float) ($payload['discount'] ?? 0);
            $totalPrice = $totalAmount - $discount + $deliveryFee;
            if ($totalPrice < 0) {
                $totalPrice = 0;
            }

            $paymentStatus = PaymentStatus::PENDING;
            if ($paymentMethod === 'keshbek') {
                if ($user->balance < $totalPrice) {
                    throw new Exception("Keshbek balansi yetarli emas (Mavjud: " . number_format($user->balance, 0, '.', ' ') . " ball, Kerak: " . number_format($totalPrice, 0, '.', ' ') . " UZS).");
                }
                $user->decrement('balance', $totalPrice);
                $paymentStatus = PaymentStatus::PAID;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $storeId,
                'branch_id' => $branch?->id,
                'short_code' => $shortCode,
                'total_price' => $totalPrice,
                'subtotal' => $totalAmount,
                'delivery_price' => $deliveryFee,
                'discount' => $discount,
                'commission_amount' => $commissionAmount,
                'status' => OrderStatus::NEW,
                'order_type' => $payload['type'],
                'payment_method' => $paymentMethod,
                'payment_owner' => $paymentOwner,
                'payment_status' => $paymentStatus,
                'shipping_address' => [
                    'address' => $customerAddress,
                    'latitude' => $payload['type'] === 'pickup' ? ($branch?->latitude ?? null) : ($payload['latitude'] ?? null),
                    'longitude' => $payload['type'] === 'pickup' ? ($branch?->longitude ?? null) : ($payload['longitude'] ?? null),
                ],
                'notes' => $payload['notes'] ?? null,
            ]);

            // Create initial payment log for consistent transaction records
            Payment::create([
                'order_id' => $order->id,
                'provider' => $order->payment_method,
                'amount' => $totalPrice,
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
                $variantModel = $data['variant_model'];
                $variantModel->decrement('stock', $data['quantity']);
            }

            // 4. Create Status History
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => OrderStatus::NEW->value,
                'note' => 'Order created via Mini App',
            ]);
            
            event(new \App\Events\OrderCreated($order));

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
