<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

#[Group('Owner: settings', "Do'kon sozlamalari va fayl yuklash (ega).")]
class OwnerSettingsController extends Controller
{
    use ApiResponse;

    /**
     * Get store settings.
     */
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
        foreach (Store::unavailablePaymentMethods() as $method) {
            $paymentSettings[$method] = ['enabled' => false, 'available' => false];
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
        ]);
    }

    /**
     * Update store settings.
     */
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

        $currentSettings = $store->payment_settings ?? [];
        $newSettings = $request->payment_settings ?? [];

        foreach (Store::unavailablePaymentMethods() as $method) {
            $requested = $newSettings[$method] ?? null;
            if ((bool) (is_array($requested) ? ($requested['enabled'] ?? false) : $requested)) {
                throw ValidationException::withMessages([
                    'payment_settings' => ucfirst($method) . " orqali to'lov hozircha mavjud emas, uni yoqib bo'lmaydi.",
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
            + $request->only(['name', 'timezone'])); // P2-12

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
