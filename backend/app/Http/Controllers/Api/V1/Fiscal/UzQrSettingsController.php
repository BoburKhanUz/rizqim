<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Pos\QrPayments\Providers\UzQrProvider;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrProviders;
use App\Domain\Pos\QrPayments\UzQr\UzQrTillKeys;
use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Store;
use App\Models\User;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wave P: the UzQR credentials of the store for the partner web — each POS till's own API key (the restaurant creates
 * one dynamic QR and one API key per till in uzqr.uz) and the store's STIR (its fiscal settings' `tin`). The key is
 * write-only: answers carry `has_api_key` and its last four characters at most. settings.manage, never from a
 * terminal PIN session (as the other fiscal settings); audited (devices.uzqr_key_changed, without the key).
 */
#[Group('Owner: fiscal', "Fiskal sozlamalar (STIR, soliq rejimi, drayver, xizmatlar MXIK kodlari), fiskal cheklar ro'yxati va qayta yuborish (P3-11).")]
class UzQrSettingsController extends ManagementController
{
    public const OVERVIEW = 'array{driver: string|null, tin: string|null, tin_set: bool, tills: list<' . UzQrTillKeys::VIEW . '>}';

    /**
     * UzQR credentials
     *
     * The store's UzQR driver (`fiscal_settings.uzqr.driver`; null: UzQR not offered), its STIR (`tin`, set in the
     * fiscal settings) and its POS tills (`branch_id` narrows to one; a branch-bound caller: theirs) with whether each
     * has its UzQR API key (`has_api_key`, `api_key_last4`, `api_key_set_at`) and whether it can make a UzQR now
     * (`ready`; else `message` says why). The key itself is never returned. settings.manage.
     */
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::OVERVIEW . '}')]
    public function index(Request $request, UzQrTillKeys $keys): JsonResponse
    {
        $store = $this->currentStore($request);
        $request->validate(['branch_id' => UzValidation::rowId()]);
        $only = $this->branchFilter($request, Permission::SETTINGS_MANAGE);
        $tills = Device::query()
            ->where('store_id', $store->id)
            ->where('type', DeviceType::POS->value)
            ->whereNull('revoked_at')
            ->when($only !== null, fn ($query) => $query->where('branch_id', $only?->id))
            ->orderBy('id')
            ->get();

        return $this->success([
            'driver' => FiscalSettings::of($store)->uzqrDriver(),
            'tin' => $keys->tin($store),
            'tin_set' => $keys->tin($store) !== null,
            'tills' => $tills->map(fn (Device $device) => $keys->present($device, $this->notReady($device, $store)))->values()->all(),
        ]);
    }

    /**
     * Set a till's UzQR API key
     *
     * `api_key`: the key the restaurant created for this till's dynamic QR in uzqr.uz (shown there once): 8–512
     * printable characters without spaces. Left out, null, empty or masked (`••••1234`, `****`): the stored key is
     * kept. `remove_api_key: true` clears it (the till then offers the other methods). Never returned: the answer has
     * `has_api_key` and `api_key_last4`. Audited. settings.manage on the till's branch; a till of another store or a
     * revoked one: 404.
     */
    #[BodyParameter('api_key', description: "Kassaning UzQR API kaliti (yashirin; maskalangan qiymat saqlanganini o'zgartirmaydi).", required: false, type: 'string|null')]
    #[BodyParameter('remove_api_key', required: false, type: 'bool')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . UzQrTillKeys::VIEW . '}')]
    public function updateTill(Request $request, UzQrTillKeys $keys, string $uuid): JsonResponse
    {
        $store = $this->currentStore($request);
        $input = $request->only(['api_key', 'remove_api_key']);
        if (UzQrTillKeys::keeps($input['api_key'] ?? null)) {
            unset($input['api_key']);
        }
        $data = validator($input, [
            'api_key' => ['sometimes', 'string', 'regex:' . UzQrTillKeys::KEY_PATTERN],
            'remove_api_key' => ['sometimes', 'boolean'],
        ], UzValidation::messages([
            'api_key.regex' => "API kalit 8–512 belgidan iborat, bo'sh joysiz bo'lishi kerak (uzqr.uz da ko'rsatilganidek nusxalang).",
        ]), ['api_key' => 'UzQR API kaliti', 'remove_api_key' => "Kalitni o'chirish"])->validate();
        if (isset($data['api_key']) && ($data['remove_api_key'] ?? false)) {
            throw ValidationException::withMessages(['remove_api_key' => "Kalitni bir vaqtda kiritib va o'chirib bo'lmaydi."]);
        }

        // Tenancy: a live POS till of the caller's store
        $device = Str::isUuid($uuid) ? Device::where('store_id', $store->id)->where('uuid', $uuid)
            ->where('type', DeviceType::POS->value)->whereNull('revoked_at')->first() : null;
        $branch = $device?->branch_id !== null ? Branch::where('store_id', $store->id)->find($device->branch_id) : null;
        if ($device === null || $branch === null) {
            return $this->error('Kassa topilmadi.', [], 404);
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $branch);
        /** @var User $user */
        $user = $request->user();

        if ($data['remove_api_key'] ?? false) {
            $keys->set($device, null, $user);
        } elseif (isset($data['api_key'])) {
            $keys->set($device, (string) $data['api_key'], $user);
        }

        return $this->success($keys->present($device->refresh(), $this->notReady($device, $store)), 'Saqlandi.');
    }

    private function notReady(Device $device, Store $store): ?string
    {
        $provider = app(QrProviders::class)->for($store, QrProvider::UZQR);

        return $provider instanceof UzQrProvider ? $provider->readyFor($device, $store) : "UzQR shlyuzi sozlanmagan";
    }
}
