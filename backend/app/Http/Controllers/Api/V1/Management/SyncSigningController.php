<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Sync\SignaturePolicy;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4, owner answer 4): the store's mode for the signatures of the tills' offline
 * queue, for the partner web panel (pos-setup). Observe (the default): what each signature was worth is only recorded.
 * Enforce: an unsigned or badly signed operation is still applied and shown to the manager (`signature_invalid`; a
 * sale is never dropped), only a concession — a discount, a void — is refused; a payment is recorded and flagged
 * (`payment_unverified`), an operation after a lost one is applied and shown to the manager (`chain_gap`). settings.manage, store-wide, never from a terminal PIN session; audited
 * (settings.pos_sync_signatures_changed). The tills' keys are never part of these answers, and there is no "renew the
 * key" action here: a till renews its own key (POST /terminal/sync-key) when its queue is empty.
 */
#[Group('Owner: POS settings', "Kassa navbati imzosi: kuzatish yoki qat'iy rejim (M5 S3).")]
class SyncSigningController extends ManagementController
{
    /**
     * The signature mode of the offline queue
     *
     * `enforce`: false observe, true enforce. `unsigned_tills`: the store's connected tills that have not shown that
     * they sign (no verified operation received from them under their current key — a key on the server alone proves
     * nothing — or the last operation received from them came unsigned: an old app) — in enforce what they queue
     * offline is applied but every operation is flagged for the manager, their discounts and voids refused;
     * `signing_tills`: how many of the others there are.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . SignaturePolicy::VIEW . '}')]
    public function show(Request $request): JsonResponse
    {
        return $this->success(app(SignaturePolicy::class)->present($this->currentStore($request)));
    }

    /**
     * Change the signature mode of the offline queue
     *
     * `enforce` true or false. Turning it on while a connected till does not sign yet answers 409 `tills_not_signing`
     * with those tills; `confirm_unsigned: true` turns it on all the same. Store-wide: a caller bound to one branch is
     * refused (403 `store_wide_only`). Audited (settings.pos_sync_signatures_changed). Repeating it changes nothing.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . SignaturePolicy::VIEW . '}')]
    #[ApiDocResponse(409, 'A connected till does not sign yet: confirm with confirm_unsigned (tills_not_signing)', type: 'array{success: false, message: string, data: array{reason: string, unsigned_tills: list<array{uuid: string, name: string, branch_id: int|null, has_key: bool, last_sig_status: string|null}>}}')]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enforce' => 'required|boolean',
            'confirm_unsigned' => 'nullable|boolean',
        ]);
        $this->assertStoreWide($request, "Imzo rejimi butun do'kon uchun: uni faqat do'kon egasi o'zgartiradi.");

        $store = $this->currentStore($request);
        $policy = app(SignaturePolicy::class);
        $enforce = (bool) $data['enforce'];
        $unsigned = $policy->present($store)['unsigned_tills'];
        if ($enforce && !$store->pos_sync_enforce_signatures && $unsigned !== [] && !($data['confirm_unsigned'] ?? false)) {
            $names = implode(', ', array_map(fn (array $till) => $till['name'], array_slice($unsigned, 0, 5)));

            return $this->error(
                "Hali imzolamaydigan kassa bor ({$names}): qat'iy rejimda ularning internetsiz amallari qo'llanadi, lekin har biri menejer tekshirishi uchun belgilanadi, chegirma va taomni o'chirish esa rad etiladi. Avval kassa ilovasini yangilang yoki baribir yoqishni tasdiqlang.",
                ['reason' => 'tills_not_signing', 'unsigned_tills' => $unsigned],
                409,
            );
        }

        $policy->setEnforce($store, $enforce, $request->user());

        return $this->success($policy->present($store), $enforce ? "Qat'iy rejim yoqildi." : 'Kuzatish rejimi yoqildi.');
    }
}
