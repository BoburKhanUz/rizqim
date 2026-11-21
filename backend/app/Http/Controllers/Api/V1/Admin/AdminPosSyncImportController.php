<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Pos\Sync\PosSyncImport;
use App\Domain\Pos\Sync\PosSyncImportException;
use App\Http\Controllers\Api\V1\Management\SyncImportController;
use App\Http\Requests\Admin\AdminRules;
use App\Models\Device;
use App\Services\Admin\AdminAudit;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Wave M5 S4-b (docs/scale/M5_DESIGN.md §5): the control centre imports a till's rescue file for any store, when the
 * owner cannot (docs/OPERATIONS.md, the rescue runbook). admin.stores.manage, a step-up and a `reason`. The store's
 * own journal gets `pos.sync_imported` (PosSyncImport), the control centre's the route's entry beside it.
 */
#[Group('Admin')]
class AdminPosSyncImportController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    /**
     * Import a till's queue file (any store)
     *
     * A multipart upload: `file` (the `.rzq` file a till wrote, at most 8 MB and 10 000 operations) and `reason`. The
     * same import as the owner's `POST /owner/pos/sync/import`, for a till of any store: found by the file's header,
     * the file's MAC checked, every operation applied as that till once by its `op_id`. The answer is the same
     * (`results`, `skipped`, `totals`, `complete`; `complete: false` — upload the same file again). Refused whole with
     * 422 (`not_export_file`, `version_unsupported`, `file_too_large`, `not_a_till`, `key_unavailable`,
     * `file_mac_invalid`) or 404 (`device_not_found`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosSyncImport::VIEW . '}')]
    #[ApiDocResponse(404, 'No such till (device_not_found)', type: self::ERROR)]
    #[ApiDocResponse(422, 'The whole file is refused: not_export_file, version_unsupported, file_too_large, not_a_till, key_unavailable, file_mac_invalid', type: self::ERROR)]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // The .rzq file written by the till ("Navbatni faylga saqlash"), at most 8 MB.
            'file' => 'required|file|max:8192',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $outcome = app(PosSyncImport::class)->import((string) $file->get(), $this->member($request), null);
        } catch (PosSyncImportException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status);
        }

        $device = Device::query()->where('uuid', $outcome['file']['device'])->first();
        AdminAudit::for($request)->subject($device)->store($device?->store_id !== null ? (int) $device->store_id : null)->meta([
            'device' => $outcome['file']['device'],
            'first_seq' => $outcome['file']['first_seq'],
            'last_seq' => $outcome['file']['last_seq'],
            'totals' => $outcome['totals'],
            'complete' => $outcome['complete'],
        ]);

        return $this->success($outcome, SyncImportController::summary($outcome));
    }
}
