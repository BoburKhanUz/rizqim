<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Sync\PosSyncImport;
use App\Domain\Pos\Sync\PosSyncImportException;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Wave M5 S4-b (docs/scale/M5_DESIGN.md §5): the rescue of a till's offline queue from the file the till wrote
 * ("Navbatni faylga saqlash"), for the partner web panel. devices.manage (the owner, a manager), never from a terminal
 * PIN session; a manager bound to one branch reaches that branch's tills only; another store's till is not found.
 * Audited (pos.sync_imported). The till's key is never part of these answers.
 */
#[Group('Owner: POS settings', "Kassa navbatini fayldan tiklash (M5 S4).")]
class SyncImportController extends ManagementController
{
    /**
     * Import a till's queue file
     *
     * A multipart upload: `file`, the `.rzq` file a till wrote (at most 8 MB and 10 000 operations). The server finds
     * the till by the file's header (it may be disconnected or its token expired; its key is kept 30 days after it was
     * disconnected), checks the file's MAC and then applies every operation as that till, exactly like
     * `POST /pos/sync`: once by its `op_id` (a file uploaded twice, or after the till sent a part of it, changes
     * nothing: `duplicate: true`), with the same signature check (`sig_status`) and store mode — a payment is recorded,
     * never dropped. `results`: one entry per operation that has a row on the server, as the sync endpoint answers it,
     * with its `line` in the file, the `device` it was applied as and `renumbered_from` (its number in the file when
     * that number already belonged to another operation and it was given the next free one). `skipped`: lines nothing
     * was recorded for (`invalid_payload`: no usable operation; `device_unknown`: queued under a device that is not a
     * disconnected till of the same branch; `signature_invalid`: such a line not signed by that till's own key;
     * `repeated_line`; `op_id_conflict`). An operation dated after its till was disconnected is applied and a conflict
     * `device_revoked`, one older than 12 months a conflict `operation_too_old`: a discount or a void of those is
     * refused, a payment recorded (a conflict), a sale never dropped. `warnings`: `earlier_numbers_missing` — the
     * file's numbers start after ones the server does not hold for the till (upload its earlier file too); the file was
     * applied all the same. A refused file is audited (`pos.sync_import_refused`). `complete: false`: the time for one request
     * ran out (`stopped_reason: time_budget`), the till itself is syncing (`sync_in_progress`) or the database failed
     * (`database`); `totals.pending` operations are left: upload the same file again.
     * The whole file is refused (nothing applied) with 422 and `data.reason`: `not_export_file`, `version_unsupported`,
     * `file_too_large`, `not_a_till`, `key_unavailable` (no key to check it with), `file_mac_invalid`; a till that is
     * not this store's: 404 `device_not_found`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosSyncImport::VIEW . '}')]
    #[ApiDocResponse(404, 'The till of the file is not this store\'s (device_not_found)', type: 'array{success: false, message: string, data: array{reason: string}}')]
    #[ApiDocResponse(422, 'The whole file is refused: not_export_file, version_unsupported, file_too_large, not_a_till, key_unavailable, file_mac_invalid', type: 'array{success: false, message: string, data: array{reason: string}}')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // The .rzq file written by the till ("Navbatni faylga saqlash"), at most 8 MB.
            'file' => 'required|file|max:8192',
        ], ['file.max' => 'Fayl juda katta: eng ko\'pi 8 MB.', 'file.required' => 'Faylni tanlang.', 'file.file' => 'Faylni tanlang.', 'file.uploaded' => 'Fayl yuklanmadi: u juda katta bo\'lishi mumkin (eng ko\'pi 8 MB).']);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $branchId = $this->permissions->isBranchScoped($user) ? (int) $user->branch_id : null;

        try {
            $outcome = app(PosSyncImport::class)->import((string) $file->get(), $user, $this->storeId($request), $branchId);
        } catch (PosSyncImportException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason], $e->status);
        }

        return $this->success($outcome, self::summary($outcome));
    }

    /**
     * The answer's message: what happened, in one line.
     *
     * @param  array<string, mixed>  $outcome
     */
    public static function summary(array $outcome): string
    {
        $totals = $outcome['totals'];
        $new = $totals['applied'] + $totals['conflict'] + $totals['rejected'];
        $text = "Fayl o'qildi: {$new} ta amal yozildi, {$totals['duplicate']} tasi avval kelgan.";
        if ($totals['skipped'] > 0) {
            $text .= " {$totals['skipped']} ta qator o'tkazib yuborildi.";
        }
        if (!$outcome['complete']) {
            $text .= " {$totals['pending']} ta amal qoldi: shu faylni yana bir marta yuklang.";
        }

        return $text;
    }
}
