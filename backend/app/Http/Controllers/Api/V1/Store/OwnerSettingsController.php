<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Enums\UserRole;
use App\Enums\StoreStatus;
use Illuminate\Validation\ValidationException;

#[Group('Owner: settings', "Do'kon sozlamalari va fayl yuklash (ega).")]
class OwnerSettingsController extends Controller
{
    use ApiResponse;

    /** A payment provider as the owner sees it: secrets never, only whether one is set (has_secret_key...). */
    private const PROVIDER_OUT = 'array{enabled?: bool, merchant_id?: string|null, service_id?: string|null, available?: bool, unavailable_reason?: \'not_integrated\'|\'switched_off\', has_secret_key?: bool}';

    /** The store's payment_settings JSON as GET /owner/settings sends it (a legacy provider may be a bare boolean). */
    private const SETTINGS_OUT = 'array{cash?: bool|' . self::PROVIDER_OUT . ', click?: bool|' . self::PROVIDER_OUT . ', payme?: bool|' . self::PROVIDER_OUT . ', uzum?: bool|' . self::PROVIDER_OUT . ', paynet?: bool|' . self::PROVIDER_OUT . ', delivery_fee?: float|int|string, delivery_enabled?: bool, pickup_enabled?: bool, working_hours?: array{open?: string|null, close?: string|null}, allowed_methods?: list<string>}';

    /** A provider in POST /owner/settings: `enabled`; with an own merchant account its credentials (a blank or masked secret keeps the stored one). */
    private const PROVIDER_IN = 'array{enabled?: bool, merchant_id?: string|null, service_id?: string|null, secret_key?: string|null}';


    /**
     * Get store settings.
     *
     * A provider not offered now is `{enabled: false, available: false, unavailable_reason}`: `not_integrated` (Uzum,
     * Paynet) or, P4-32, `switched_off` (the RIZQIM team switched it off for a while). `telegram.group_link`: the bot
     * link that routes the store's orders and notices to a Telegram group (null while the bot has no username);
     * `group_linked`: whether a group is linked.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, name: string, timezone: string, logo: string|null, logo_url: string|null, banner: string|null, banner_url: string|null, description: string|null, phone: string|null, address: string|null, location_url: string|null, is_open: bool, min_order_amount: float, payment_settings: ' . self::SETTINGS_OUT . ', payment_account_type: \'own_account\'|\'platform_account\', status: StoreStatus, is_order_locked: bool, allowed_methods: list<string>, tin: string|null, tax_regime: \'vat\'|\'vat_simplified\'|\'turnover\'|null, telegram: array{group_link: string|null, group_linked: bool}, fiscal_settings: array{enabled: bool, driver: string, active_driver: string, charges: array<string, ' . \App\Http\Controllers\Api\V1\Fiscal\FiscalSettingsController::CHARGE . '>, sandbox: array{latency_ms: int, fail_attempts: int, failure_rate: int, reject: bool}, deposit_refund_receipt: bool|null, retained_deposit_receipt: bool|null}}}')]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        $paymentSettings = $this->settingsForOwner($store->payment_settings ?? []);
        // P4-32: `unavailable_reason` tells "not integrated yet" from "switched off by the RIZQIM team for a while"
        foreach (Store::unavailablePaymentMethods() as $method) {
            $paymentSettings[$method] = ['enabled' => false, 'available' => false, 'unavailable_reason' => Store::paymentMethodUnavailableReason($method) ?? 'not_integrated'];
        }

        return $this->success([
            'id' => $store->id,
            'name' => $store->name,
            'timezone' => $store->timezone ?: 'Asia/Tashkent', // P2-12: reports and branch hours use it
            'logo' => $store->logo,
            'logo_url' => $store->logo_url,
            'banner' => $store->banner,
            'banner_url' => $store->banner_url,
            'description' => $store->description,
            'phone' => $store->phone,
            'address' => $store->address,
            'location_url' => $store->location_url,
            'is_open' => (bool) $store->is_open,
            'min_order_amount' => (float) $store->min_order_amount,
            'payment_settings' => $paymentSettings,
            'payment_account_type' => $store->payment_account_type,
            'status' => $store->status->value ?? $store->status,
            'is_order_locked' => $store->is_order_locked,
            'allowed_methods' => array_values(array_filter(
                (array) ($store->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']),
                fn ($method) => Store::isPaymentMethodAvailable((string) $method)
            )),
            // P3-11: the store as a taxpayer and its fiscal settings (also GET /owner/fiscal/settings)
            'tin' => $store->tin,
            'tax_regime' => $store->tax_regime,
            // P4-32 (was the old store panel's Telegram tab): the bot link that routes the store's orders and notices to a
            // Telegram group; the owner or a store-wide manager opens it in the group (TelegramMessageHandler)
            'telegram' => [
                'group_link' => filled(config('services.telegram.bot_username'))
                    ? 'https://t.me/' . config('services.telegram.bot_username') . '?start=store_' . $store->uuid
                    : null,
                'group_linked' => filled($store->telegram_admin_chat_id),
            ],
            'fiscal_settings' => \App\Domain\Fiscal\FiscalSettings::of($store)->toArray(),
        ]);
    }

    /**
     * Update store settings.
     *
     * `payment_settings` is an object (not a list): the delivery fee and switches, working hours and one entry per
     * provider. A provider that cannot be enabled yet (uzum, paynet) is a 422 on `payment_settings`.
     *
     * P3-11: optional `tin` (9 digits), `tax_regime` (vat | vat_simplified | turnover) and `fiscal_settings`, as
     * PUT /owner/fiscal/settings takes them; left out = kept.
     */
    // The nested rules below describe the object (BodyParameter); left in, Scramble would fold them into a list
    #[IgnoreParam('payment_settings.delivery_fee')]
    #[IgnoreParam('payment_settings.delivery_enabled')]
    #[IgnoreParam('payment_settings.pickup_enabled')]
    #[IgnoreParam('payment_settings.working_hours')]
    #[IgnoreParam('payment_settings.working_hours.open')]
    #[IgnoreParam('payment_settings.working_hours.close')]
    #[IgnoreParam('payment_settings.*.merchant_id')]
    #[IgnoreParam('payment_settings.*.service_id')]
    #[IgnoreParam('payment_settings.*.secret_key')]
    #[BodyParameter('tin', description: "P3-11: STIR, 9 ta raqam.", required: false, type: 'string|null')]
    #[BodyParameter('tax_regime', description: 'P3-11: vat | vat_simplified | turnover.', required: false, type: "'vat'|'vat_simplified'|'turnover'|null")]
    #[BodyParameter('fiscal_settings', description: 'P3-11: fiskal sozlamalar (PUT /owner/fiscal/settings kabi).', required: false, infer: false, type: 'array{enabled?: bool, driver?: string|null, charges?: array<string, array{name?: string|null, mxik?: string|null, package_code?: string|null, unit?: string|null}>, sandbox?: array{latency_ms?: int, fail_attempts?: int, failure_rate?: int, reject?: bool}, deposit_refund_receipt?: bool|null, retained_deposit_receipt?: bool|null}')]
    #[BodyParameter('payment_settings', description: "To'lov va yetkazish sozlamalari (obyekt).", required: true, infer: false, type: 'array{delivery_fee?: float|null, delivery_enabled?: bool|null, pickup_enabled?: bool|null, working_hours?: array{open?: string|null, close?: string|null}|null, cash?: ' . self::PROVIDER_IN . ', click?: ' . self::PROVIDER_IN . ', payme?: ' . self::PROVIDER_IN . ', uzum?: ' . self::PROVIDER_IN . ', paynet?: ' . self::PROVIDER_IN . '}')]
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        $request->validate([
            // P2-12: the store's name and timezone (the panel's settings page edits them too); left out = kept
            'name' => 'sometimes|required|string|max:255',
            'timezone' => 'sometimes|required|string|timezone:all',
            'logo' => 'nullable|string|max:500',
            'banner' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'location_url' => 'nullable|string|max:1000',
            'is_open' => 'required|boolean',
            'min_order_amount' => 'nullable|numeric|min:0',
            'payment_settings' => 'required|array',
            'payment_settings.delivery_fee' => 'nullable|numeric|min:0',
            'payment_settings.delivery_enabled' => 'nullable|boolean',
            'payment_settings.pickup_enabled' => 'nullable|boolean',
            'payment_settings.working_hours' => 'nullable|array',
            'payment_settings.working_hours.open' => 'nullable|string',
            'payment_settings.working_hours.close' => 'nullable|string',
            'payment_settings.*.merchant_id' => 'nullable|string|max:255',
            'payment_settings.*.service_id' => 'nullable|string|max:255',
            'payment_settings.*.secret_key' => 'nullable|string|max:255',
        ], [
            'name.required' => "Do'kon nomini kiriting.",
            'name.max' => "Do'kon nomi ko'pi bilan 255 belgi bo'lishi mumkin.",
            'timezone.required' => 'Vaqt mintaqasini kiriting.',
            'timezone.timezone' => "Vaqt mintaqasi noto'g'ri (masalan Asia/Tashkent).",
        ]);

        // P3-11: TIN, tax regime and fiscal settings (validated before anything is saved)
        $fiscalUpdate = \App\Domain\Fiscal\FiscalSettings::validatedUpdate($request->only(['tin', 'tax_regime', 'fiscal_settings']), $store);

        $currentSettings = $store->payment_settings ?? [];
        $newSettings = $request->payment_settings ?? [];

        foreach (Store::unavailablePaymentMethods() as $method) {
            $requested = $newSettings[$method] ?? null;
            if ((bool) (is_array($requested) ? ($requested['enabled'] ?? false) : $requested)) {
                throw ValidationException::withMessages([
                    'payment_settings' => Store::paymentMethodUnavailableReason($method) === 'switched_off'
                        ? ucfirst($method) . " orqali to'lovni RIZQIM vaqtincha o'chirgan, uni yoqib bo'lmaydi."
                        : ucfirst($method) . " orqali to'lov hozircha mavjud emas, uni yoqib bo'lmaydi.",
                ]);
            }
        }

        $currentSettings['delivery_fee'] = $newSettings['delivery_fee'] ?? ($currentSettings['delivery_fee'] ?? 0);
        $currentSettings['working_hours'] = $newSettings['working_hours'] ?? ($currentSettings['working_hours'] ?? []);

        // Whether checkout offers delivery / pickup (CreateOrderAction); a client that leaves a switch out keeps it
        foreach (['delivery_enabled', 'pickup_enabled'] as $switch) {
            if (isset($newSettings[$switch])) {
                $currentSettings[$switch] = (bool) $newSettings[$switch];
            }
        }

        foreach (['cash', 'click', 'payme', 'uzum', 'paynet'] as $provider) {
            // A method not offered yet keeps whatever the store saved for it
            if (isset($newSettings[$provider]) && Store::isPaymentMethodAvailable($provider)) {
                if (!isset($currentSettings[$provider]) || !is_array($currentSettings[$provider])) {
                    $currentSettings[$provider] = [];
                }
                $currentSettings[$provider]['enabled'] = (bool) ($newSettings[$provider]['enabled'] ?? false);

                // Own merchant credentials, as in the panel; a blank or masked secret keeps the stored one (see Store).
                if ($store->payment_account_type === 'own_account' && is_array($newSettings[$provider])) {
                    foreach (['merchant_id', 'service_id', 'secret_key'] as $field) {
                        if (array_key_exists($field, $newSettings[$provider])) {
                            $currentSettings[$provider][$field] = $newSettings[$provider][$field];
                        }
                    }
                }
            }
        }

        $store->update([
            'logo' => $request->logo,
            'banner' => $request->banner,
            'description' => $request->description,
            'address' => $request->address,
            'location_url' => $request->location_url,
            'is_open' => $request->is_open,
            'min_order_amount' => $request->min_order_amount ?? 0,
            'payment_settings' => $currentSettings,
        ] + ($request->has('phone') ? ['phone' => $request->phone] : []) // a client that never sends phone keeps it
            + $request->only(['name', 'timezone']) // P2-12
            + $fiscalUpdate); // P3-11

        // The main (first) branch follows the store's address, and its location when the link holds one
        $branch = $store->branches()->oldest('id')->first();
        if (!$branch) {
            $branch = $store->branches()->create([
                'name' => 'Asosiy filial',
                'status' => \App\Enums\BranchStatus::ACTIVE,
            ]);
        }

        $branchUpdate = ['address' => $request->address];

        $point = $this->coordinatesFrom($request->location_url);
        if ($point !== null) {
            $branchUpdate['latitude'] = $point['lat'];
            $branchUpdate['longitude'] = $point['lng'];
        }

        $branch->update($branchUpdate);

        return $this->success($store, 'Do\'kon sozlamalari muvaffaqiyatli yangilandi.');
    }

    /**
     * The point a map link shows: Yandex's pt= / ll= (longitude first), or Google's q= / query= / @ / place/ (latitude
     * first). Null when the link holds no real coordinates (nothing matched, or a value out of range), so the branch
     * keeps the location it has rather than getting a made-up one.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coordinatesFrom(?string $url): ?array
    {
        if (!$url) {
            return null;
        }

        $url = rawurldecode($url); // shared Yandex links write the comma as %2C
        $pair = '(-?\d{1,3}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)';

        if (preg_match("~[?&](?:pt|ll)={$pair}~", $url, $matches)) {
            [$lng, $lat] = [(float) $matches[1], (float) $matches[2]];
        } elseif (preg_match("~(?:[?&](?:q|query)=|@|/place/){$pair}~", $url, $matches)) {
            [$lat, $lng] = [(float) $matches[1], (float) $matches[2]];
        } else {
            return null;
        }

        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Secrets are never sent back; the owner only sees which ones are configured (has_secret_key...).
     */
    private function settingsForOwner(array $settings): array
    {
        $public = [];

        foreach ($settings as $key => $value) {
            if (Store::isSecretKey($key)) {
                $public["has_{$key}"] = filled($value);
            } else {
                $public[$key] = is_array($value) ? $this->settingsForOwner($value) : $value;
            }
        }

        return $public;
    }

    /**
     * Upload store asset.
     */
    public function upload(Request $request): JsonResponse
    {
        if ($request->user()->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('file')) {
            $path = \App\Services\ImageOptimizer::optimizeAndStore($request->file('file'), 'store_assets', 1000, 75);
            return $this->success([
                'path' => $path,
                'url' => asset('storage/' . $path),
            ], 'Fayl muvaffaqiyatli yuklandi.');
        }

        return $this->error('Fayl yuklashda xatolik yuz berdi.', [], 400);
    }
}
