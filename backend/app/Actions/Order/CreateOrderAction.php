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
use App\Enums\MenuUnit;
use App\Enums\OrderChannel;
use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\MenuBatch;
use App\Domain\Delivery\DeliveryQuote;
use App\Domain\Delivery\DeliveryQuoter;
use App\Domain\Delivery\DeliveryUnavailableException;
use App\Domain\Delivery\ZoneResolver;
use App\Services\Catalog\BatchService;
use App\Services\Catalog\ChannelMenu;
use App\Services\Catalog\MenuAvailability;
use App\Services\Order\DiningFeeCalculator;
use App\Services\Order\OrderLineBuilder;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use App\Services\Promo\InvalidPromoCodeException;
use App\Services\Promo\PromoCodeService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class CreateOrderAction
{
    public function __construct(
        protected PromoCodeService $promoCodes,
        // P1-21: menu lines (units, modifiers, channel prices), batch stock, service charge / place fee
        protected OrderLineBuilder $lines,
        protected BatchService $batches,
        protected DiningFeeCalculator $diningFees,
        // P1-32: delivery zones price and route delivery orders (docs/ZONES.md)
        protected DeliveryQuoter $deliveryQuoter,
        protected ZoneResolver $zones,
    ) {}

    /**
     * @param \App\Models\User $user
     * @param array $items Array of ['variant_id' => uuid, 'quantity' => int]
     * @param array $payload Additional checkout data (type, address, notes, payment_method, promo_code)
     */
    public function execute($user, array $items, array $payload): Order
    {
        // Nothing could ever mark such an order paid (no webhook for it yet)
        $method = (string) ($payload['payment_method'] ?? 'cash');
        if (!\App\Models\Store::isPaymentMethodIntegrated($method)) {
            throw ValidationException::withMessages(['payment_method' => "Bu to'lov usuli hozircha mavjud emas"]);
        }
        // P4-32: a provider the RIZQIM team switched off (Tizim → To'lov tizimlari): 422 with code payment_provider_inactive
        if (!\App\Models\Store::isPaymentMethodAvailable($method)) {
            throw \App\Services\Payment\PaymentProviderSwitch::refusal($method);
        }

        return DB::transaction(function () use ($user, $items, $payload) {
            
            $storeId = $payload['store_id'];
            $store = \App\Models\Store::findOrFail($storeId);

            // 1. Store Status & Working Hours Validation
            $storeStatus = $store->status->value ?? $store->status;
            // A suspended or rejected store, or one switched off, takes no Mini App orders (suspending only hid it from
            // the catalog, so a stale cart or a direct request still got through)
            if (!$store->is_active
                || $storeStatus === \App\Enums\StoreStatus::SUSPENDED->value
                || $storeStatus === \App\Enums\StoreStatus::REJECTED->value) {
                throw new Exception("Restoran hozircha buyurtma qabul qilmayapti.");
            }
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
            // P1-32: a store with active delivery zones routes and prices delivery by them; without zones, the radius rule
            $deliveryQuote = null;
            $zoneDelivery = $payload['type'] === 'delivery' && $this->zones->hasZones($store);
            
            if ($payload['type'] === 'pickup') {
                $branch = \App\Models\Branch::where('id', $payload['branch_id'])
                    ->where('store_id', $storeId)
                    ->where('status', \App\Enums\BranchStatus::ACTIVE->value)
                    ->first();
                if (!$branch) {
                    throw new Exception("Tanlangan filial ushbu restoranga tegishli emas yoki hozir ishlamayapti.");
                }
                $customerAddress = $branch->address ?? $store->address ?? 'Restoran filiali';
            } elseif ($zoneDelivery) {
                $deliveryQuote = $this->zoneQuote($store, $payload);
                $branch = $deliveryQuote->branch;
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

            // P1-34: every line is checked against a branch's stop-list and batches; an order no branch was resolved for
            // (a hall order, which is the staff/POS path's) would skip both, so it is never placed
            if ($branch === null) {
                throw ValidationException::withMessages([
                    'type' => "Buyurtma uchun filial aniqlanmadi. Yetkazib berish yoki olib ketishni tanlang.",
                ]);
            }

            // 3. Validate variants and calculate total securely (P1-21: units, modifiers, channel prices, stop-list,
            // batches; App\Services\Order\OrderLineBuilder, docs/MENU.md)
            $totalAmount = Money::zero();
            $orderItemsData = [];
            $channel = $payload['channel'] ?? OrderChannel::TELEGRAM;
            $channel = $channel instanceof OrderChannel ? $channel : (OrderChannel::tryFrom((string) $channel) ?? OrderChannel::TELEGRAM);
            $channelMenu = ChannelMenu::for((int) $storeId, $channel);
            $lines = $this->lines->parse($items);

            // The same variant may be sent on several lines; stock is checked against the combined quantity
            $requestedQuantities = [];
            foreach ($lines as $line) {
                $uuid = $line['variant_uuid'];
                $requestedQuantities[$uuid] = ($requestedQuantities[$uuid] ?? Quantity::zero())->plus($line['quantity']);
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

            // Identical piece lines become one order line; weighed lines stay as sent (OrderLineBuilder::merge)
            $lines = $this->lines->merge($lines, $variants);

            $stopList = $this->lines->stopList($branch);

            foreach ($requestedQuantities as $uuid => $quantity) {
                $variant = $variants->get($uuid);
                
                if (!$variant) {
                    throw new Exception("Product variant not found or unavailable.");
                }

                // Check branch product variant availability override
                $branchOverride = \App\Models\BranchProductVariant::where('branch_id', $branch->id)
                    ->where('variant_id', $variant->id)
                    ->first();

                if ($branchOverride && !$branchOverride->is_active) {
                    $reason = $branchOverride->hidden_reason ? " ({$branchOverride->hidden_reason})" : "";
                    throw new Exception("Uzr, '{$variant->product->name} ({$variant->name})' mahsuloti ushbu filialda vaqtincha mavjud emas{$reason}.");
                }

                $this->assertSellable($variant, $channelMenu, $stopList);

                if (Quantity::of($variant->stock)->isLessThan($quantity)) {
                    throw $this->outOfStock($variant, $variant->stock);
                }
            }

            // Batch / kazan stock: the open batch of each line's product on the branch, locked in id order after the variants
            $batchNeeds = $this->batchNeeds($lines, $variants, $branch);

            $modifierRules = $this->lines->modifierRules($variants->map(fn ($variant) => (int) $variant->product_id)->unique()->values()->all());
            $comboBlocked = null;

            foreach ($lines as $line) {
                $variant = $variants->get($line['variant_uuid']);
                $this->lines->checkQuantity($variant, $line['quantity'], $line['index']);
                $modifiers = $this->lines->resolveModifiers(
                    $variant,
                    $line['modifiers'],
                    $modifierRules[(int) $variant->product_id] ?? [],
                    $stopList[StopListSubject::MODIFIER->value],
                    $line['index'],
                );

                // P6-31: a combo's picks checked (required slots, options of the slot, limits, availability) and priced
                $combo = null;
                $combos = app(\App\Domain\Pos\Menu\Combos::class);
                if (isset($line['combo']) || $combos->hasCombos((int) $storeId)) {
                    $comboBlocked ??= $combos->blockedFor($branch, $stopList);
                    $combo = $combos->resolve($variant, (array) ($line['combo'] ?? []), $comboBlocked, $channelMenu, (int) $line['index']);
                }
                if ($combo !== null) {
                    $modifiers['total'] = $modifiers['total']->plus($combo['delta']);
                }

                $price = $channelMenu->priceOf($variant);
                $lineTotal = $this->lines->lineTotal($price, $line['quantity'], $modifiers['total'], $variant->unit);
                
                $totalAmount = $totalAmount->plus($lineTotal);
                $batch = $batchNeeds['line_batches'][$line['index']] ?? null;
                
                // Prepare snapshot data
                $orderItemsData[] = [
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'quantity' => $line['quantity'],
                    'unit' => $variant->unit->value,
                    'unit_price' => $price->toString(),
                    'modifiers' => $modifiers['snapshot'] ?: null,
                    'modifiers_total' => $modifiers['total']->toString(),
                    'total_price' => $lineTotal->toString(),
                    'batch_id' => $batch?->id,
                    'batch_quantity' => $batch ? $line['quantity']->times(MenuAvailability::consumptionOf($variant))->toString() : null,
                    'set_parts' => app(\App\Domain\Pos\Menu\MealSets::class)->partsFor($variant), // P6-15: a kompleks keeps its parts
                    'combo_parts' => $combo['parts'] ?? null, // P6-31: a combo keeps its parts as sold
                    'variant_model' => $variant, // Temporary reference for stock deduction
                ];
            }
            
            if (!$totalAmount->isPositive()) {
                throw new Exception("Buyurtma summasi noldan katta bo'lishi kerak.");
            }

            // 4. Minimum Order Validation
            $minOrderAmount = Money::of($store->min_order_amount);
            if ($deliveryQuote !== null) {
                // The zone's minimum replaces the store's; the same zone instance, so fee, minimum and snapshot agree
                $deliveryQuote = $this->deliveryQuoter->reprice($deliveryQuote, $totalAmount);
                if ($deliveryQuote->meetsMinOrder === false) {
                    throw DeliveryUnavailableException::belowMinOrder($deliveryQuote->minOrderAmount, $totalAmount);
                }
            } elseif ($minOrderAmount->isPositive() && $totalAmount->isLessThan($minOrderAmount)) {
                throw new Exception("Eng kam buyurtma summasi: " . number_format($minOrderAmount->toFloat(), 0, '.', ' ') . " UZS.");
            }

            // 5. Radius validation is now handled during Branch Routing Engine (above)
            // Fetch delivery fee from store payment settings
            $deliveryFee = Money::zero();
            $configuredFee = $store->payment_settings['delivery_fee'] ?? 0;
            if ($deliveryQuote !== null) {
                // The zone's price (zero past its free-delivery threshold); whatever the client sent is never read
                $deliveryFee = $deliveryQuote->deliveryPrice;
            } elseif ($payload['type'] === 'delivery' && is_numeric($configuredFee)) {
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

            // P1-21: service charge and place fee, separate lines for the POS hall; never on Telegram orders
            $guests = isset($payload['guests_count']) ? (int) $payload['guests_count'] : null;
            $diningFees = $this->diningFees->calculate((int) $storeId, $branch->id, $channel, Money::max(Money::zero(), $totalAmount->minus($discount)), $guests);

            // P6-34: the branch's packaging lines for a pickup or delivery (module packaging), never discounted; a pickup
            // for later (module scheduled_pickup) is checked and gets its kitchen time
            $packaging = app(\App\Domain\Pos\Takeaway\PackagingRules::class)->forCheckout($branch, (string) $payload['type'], $orderItemsData);
            $pickup = app(\App\Domain\Pos\Takeaway\ScheduledPickup::class)->forCheckout($branch, (string) $payload['type'], $payload['pickup_at'] ?? null, $orderItemsData);

            $totalPrice = Money::max(Money::zero(), $totalAmount->minus($discount)->plus($deliveryFee))
                ->plus($diningFees['service_charge'])
                ->plus($diningFees['place_fee'])
                ->plus($packaging['amount']);

            // Same base and rounding the ledger settles on, so the stored snapshot is exactly what gets charged.
            // P4-21: the rate of the order's channel (courier, own delivery, pickup; docs/BILLING.md "Komissiya"),
            // snapshotted with the channel, so a later rate change never reaches this order
            $commissionPolicy = app(\App\Services\Finance\Billing\CommissionPolicy::class);
            $commissionChannel = $commissionPolicy->channelFor($store, $channel, (string) $payload['type']);
            $commissionRate = $commissionPolicy->rateFor($store, $commissionChannel);
            $commissionAmount = $totalPrice->percent($commissionRate);

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
                'branch_id' => $branch->id,
                'short_code' => $shortCode,
                'total_price' => $totalPrice->toString(),
                'subtotal' => $totalAmount->toString(),
                'delivery_price' => $deliveryFee->toString(),
                'discount' => $discount->toString(),
                'promo_code_id' => $promoCodeId,
                'commission_amount' => $commissionAmount->toString(),
                'commission_channel' => $commissionChannel->value,
                'commission_rate' => $commissionRate,
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
                // P1-21: channel and the POS hall's fee lines (zero for the Mini App)
                'channel' => $channel->value,
                'service_charge_amount' => $diningFees['service_charge']->toString(),
                'place_fee_amount' => $diningFees['place_fee']->toString(),
                'guests_count' => $guests,
                // P1-32: the zone as it was at checkout, so later zone edits never rewrite this order's history
                'delivery_zone_id' => $deliveryQuote?->zone->id,
                'delivery_zone_name' => $deliveryQuote?->zone->name,
                'delivery_zone_price' => $deliveryQuote?->zone->deliveryPrice()->toString(),
                'delivery_min_order_amount' => $deliveryQuote?->minOrderAmount->toString(),
                // P6-34: packaging lines and the pickup time
                'packaging_amount' => $packaging['amount']->toString(),
                'packaging_lines' => $packaging['lines'] !== [] ? $packaging['lines'] : null,
                'pickup_at' => $pickup['pickup_at'] ?? null,
                'kitchen_fire_at' => $pickup['kitchen_fire_at'] ?? null,
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
                $created = OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $data['product_variant_id'],
                    'product_name' => $data['product_name'],
                    'variant_name' => $data['variant_name'],
                    'quantity' => $data['quantity']->toString(),
                    'unit' => $data['unit'],
                    'unit_price' => $data['unit_price'],
                    'modifiers' => $data['modifiers'],
                    'modifiers_total' => $data['modifiers_total'],
                    'total_price' => $data['total_price'],
                    'batch_id' => $data['batch_id'],
                    'batch_quantity' => $data['batch_quantity'],
                    'set_parts' => $data['set_parts'],
                    'combo_parts' => $data['combo_parts'],
                ]);
                // P6-31: one kitchen line per combo part, each to its own station
                if ($data['combo_parts']) {
                    app(\App\Domain\Pos\Menu\ComboKitchen::class)->createParts($created, $data['combo_parts']);
                }
                
                // Deduct stock (conditional: never below zero, even if the row changed after it was read)
                $deducted = ProductVariant::whereKey($data['product_variant_id'])
                    ->where('stock', '>=', $data['quantity']->toString())
                    ->decrement('stock', $data['quantity']->toString()); // @phpstan-ignore argument.type
                if ($deducted !== 1) {
                    throw $this->outOfStock($data['variant_model']);
                }
            }

            // Take each batch's portions at once, so two lines of one kazan are checked against their sum
            foreach ($batchNeeds['batches'] as $need) {
                if (!$this->batches->consume($need['batch'], $need['quantity'], $need['variants'])) {
                    throw $this->soldOut($need['variants']->first(), $need['batch']);
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
            DB::afterCommit(function () use ($order) {
                event(new \App\Events\OrderCreated($order));
                // Real-time hint for the store's staff (Reverb; queued, ids and status only)
                event(new \App\Events\Broadcast\OrderCreatedBroadcast($order));
            });

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
     * Zone delivery (P1-32): the zone covering the point and the branch that cooks it, through the same DeliveryQuoter
     * as GET /delivery/quote. Without branch_id the quoter picks (the zone's branch, else the nearest open one); with
     * it, that branch must serve a zone covering the point. Unpriced here: the cart is priced after the branch is known.
     *
     * @throws DeliveryUnavailableException
     */
    private function zoneQuote(\App\Models\Store $store, array $payload): DeliveryQuote
    {
        $lat = $payload['latitude'] ?? null;
        $lng = $payload['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw ValidationException::withMessages(['latitude' => "Yetkazib berish manzilining xaritadagi joylashuvi kerak."]);
        }

        $chosen = null;
        if (!empty($payload['branch_id'])) {
            // Another store's branch reads as one that does not deliver here
            $chosen = Branch::query()->where('store_id', $store->id)->find($payload['branch_id']);
            if ($chosen === null) {
                throw DeliveryUnavailableException::branchOutsideZone();
            }
        }

        return $this->deliveryQuoter->quote($store, (float) $lat, (float) $lng, null, null, $chosen);
    }

    private function outOfStock(ProductVariant $variant, int|float|null $available = null): ValidationException
    {
        $name = $variant->product->name . ($variant->name ? " ({$variant->name})" : '');
        $unit = $variant->unit->isMeasured() ? $variant->unit->label() : 'ta';
        $availableText = $available !== null ? " (mavjud: " . Quantity::of(max(0, $available))->format() . " {$unit})" : '';

        return ValidationException::withMessages([
            'items' => "Uzr, '{$name}' mahsulotidan yetarli miqdorda qolmagan{$availableText}. Iltimos, savatni yangilang.",
        ]);
    }

    /**
     * Hidden on the order's channel, or on the branch's stop-list (P1-21): a 422 the app shows as is.
     *
     * @param  array<string, array<int, ?string>>  $stopList
     */
    private function assertSellable(ProductVariant $variant, ChannelMenu $channelMenu, array $stopList): void
    {
        $name = $this->lines->nameOf($variant);

        if ($channelMenu->isVariantHidden($variant)) {
            throw ValidationException::withMessages([
                'items' => "Uzr, '{$name}' bu kanal orqali sotilmaydi. Iltimos, savatni yangilang.",
            ]);
        }

        $product = $stopList[StopListSubject::PRODUCT->value];
        $variants = $stopList[StopListSubject::VARIANT->value];
        if (array_key_exists((int) $variant->product_id, $product) || array_key_exists((int) $variant->id, $variants)) {
            $reason = $variants[(int) $variant->id] ?? $product[(int) $variant->product_id] ?? null;

            throw ValidationException::withMessages([
                'items' => "Uzr, '{$name}' hozir ushbu filialda mavjud emas" . ($reason ? " ({$reason})" : '') . ". Iltimos, savatni yangilang.",
            ]);
        }
    }

    /**
     * The batches the order takes from (P1-21): for each line, the branch's open batch of its variant (else of its
     * product), locked in id order after the variants; refused ("tugadi") when a batch-tracked product has no open
     * batch or a batch has less left than its lines need. The decrement itself is conditional (BatchService::consume).
     *
     * @param  list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>}>  $lines
     * @param  Collection<string, ProductVariant>  $variants
     * @return array{line_batches: array<int, MenuBatch>, batches: list<array{batch: MenuBatch, quantity: Quantity, variants: Collection<int, ProductVariant>}>}
     */
    private function batchNeeds(array $lines, Collection $variants, ?Branch $branch): array
    {
        $none = ['line_batches' => [], 'batches' => []];
        $productIds = $variants->map(fn (ProductVariant $variant) => (int) $variant->product_id)->unique()->values()->all();
        if ($productIds === []) {
            return $none;
        }

        if (!$branch) {
            // No branch, no batch to take from: a batch-tracked product is sold out, never sold without stock (P1-34)
            foreach ($variants as $variant) {
                if ($variant->product->batch_tracked) {
                    throw $this->soldOut($variant);
                }
            }

            return $none;
        }

        $open = MenuBatch::query()
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $productIds)
            ->open()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lineBatches = [];
        $needs = [];
        foreach ($lines as $line) {
            $variant = $variants->get($line['variant_uuid']);
            $batch = MenuAvailability::batchFor($variant, $open->where('product_id', $variant->product_id));

            if ($batch === null) {
                if ($variant->product->batch_tracked) {
                    throw $this->soldOut($variant);
                }
                continue;
            }

            $lineBatches[$line['index']] = $batch;
            $needs[$batch->id] ??= ['batch' => $batch, 'quantity' => Quantity::zero(), 'variants' => collect()];
            $needs[$batch->id]['quantity'] = $needs[$batch->id]['quantity']->plus($line['quantity']->times(MenuAvailability::consumptionOf($variant)));
            $needs[$batch->id]['variants']->push($variant);
        }

        ksort($needs);
        foreach ($needs as $need) {
            if ($need['batch']->left()->isLessThan($need['quantity'])) {
                throw $this->soldOut($need['variants']->first(), $need['batch']);
            }

            // Every variant the batch serves, for the "now sold out" announcement
            $need['variants'] = $need['batch']->product_variant_id !== null
                ? $need['variants']->unique('id')->values()
                : ProductVariant::where('product_id', $need['batch']->product_id)->get(['id', 'product_id', 'batch_consumption']);
            $needs[$need['batch']->id] = $need;
        }

        return ['line_batches' => $lineBatches, 'batches' => array_values($needs)];
    }

    private function soldOut(ProductVariant $variant, ?MenuBatch $batch = null): ValidationException
    {
        $name = $this->lines->nameOf($variant);
        $left = $batch ? $batch->left() : Quantity::zero();
        $consumption = MenuAvailability::consumptionOf($variant);

        // What is left, in this variant's units (e.g. 2 portions of 0.7 from 1.5 left)
        $units = $consumption->isPositive() ? intdiv((int) $left->toBigDecimal()->multipliedBy(1000)->toInt(), max(1, (int) $consumption->toBigDecimal()->multipliedBy(1000)->toInt())) : 0;
        if ($variant->unit->isMeasured()) {
            $text = $left->isPositive() ? " (faqat {$left->format()} {$variant->unit->label()} qoldi)" : '';
        } else {
            $text = $units > 0 ? " (faqat {$units} ta qoldi)" : '';
        }

        return ValidationException::withMessages([
            'items' => $units > 0 || ($variant->unit->isMeasured() && $left->isPositive())
                ? "Uzr, '{$name}' yetarli emas{$text}. Iltimos, savatni yangilang."
                : "Uzr, '{$name}' tugadi. Iltimos, savatni yangilang.",
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
