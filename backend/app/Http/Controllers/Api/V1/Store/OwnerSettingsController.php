<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

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
            'payment_settings.working_hours' => 'nullable|array',
            'payment_settings.working_hours.open' => 'nullable|string',
            'payment_settings.working_hours.close' => 'nullable|string',
            'payment_settings.*.merchant_id' => 'nullable|string|max:255',
            'payment_settings.*.service_id' => 'nullable|string|max:255',
            'payment_settings.*.secret_key' => 'nullable|string|max:255',
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
        ] + ($request->has('phone') ? ['phone' => $request->phone] : [])); // a client that never sends phone keeps it

        // Parse coordinates and update main branch
        $latitude = null;
        $longitude = null;
        $locationUrl = $request->location_url;

        if ($locationUrl) {
            // Try to match Yandex pt format: pt=lng,lat
            if (preg_match('/pt=([0-9\.]+),([0-9\.]+)/', $locationUrl, $matches)) {
                $longitude = (float) $matches[1];
                $latitude = (float) $matches[2];
            }
            // Try to match LL format: ll=lng,lat
            elseif (preg_match('/ll=([0-9\.]+),([0-9\.]+)/', $locationUrl, $matches)) {
                $longitude = (float) $matches[1];
                $latitude = (float) $matches[2];
            }
            // Try to match standard lat,lng coordinates in Google/Yandex Maps URL: q=lat,lng or place/lat,lng
            elseif (preg_match('/[q@place\/]([0-9\.]+),([0-9\.]+)/', $locationUrl, $matches)) {
                $latitude = (float) $matches[1];
                $longitude = (float) $matches[2];
            }
        }

        // Find or create default branch to sync address/location
        $branch = $store->branches()->first();
        if (!$branch) {
            $branch = $store->branches()->create([
                'name' => 'Asosiy filial',
                'status' => \App\Enums\BranchStatus::ACTIVE,
            ]);
        }

        $branchUpdate = [
            'address' => $request->address,
        ];

        if ($latitude !== null && $longitude !== null) {
            $branchUpdate['latitude'] = $latitude;
            $branchUpdate['longitude'] = $longitude;
        }

        $branch->update($branchUpdate);

        return $this->success($store, 'Do\'kon sozlamalari muvaffaqiyatli yangilandi.');
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
