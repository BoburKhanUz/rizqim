<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Enums\Permission;
use App\Enums\StopListSubject;
use App\Models\StopListEntry;
use App\Services\Catalog\StopListService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Owner: stop-list', "Filial stop-listi: mahsulot, variant yoki modifikatorni vaqtincha o'chirish (P1-21).")]
class StopListController extends MenuStaffController
{
    /**
     * A branch's stop-list
     *
     * The entries in force (expired ones are not listed). Branch-bound staff always get their own branch. P2-30:
     * `created_by_name` (who put it there) for staff with stoplist.manage; null for a menu.view reader.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    public function index(Request $request, StopListService $stopList): JsonResponse
    {
        $permission = $this->permissions->has($request->user(), Permission::STOPLIST_MANAGE) ? Permission::STOPLIST_MANAGE : Permission::MENU_VIEW;
        $branch = $this->branch($request, $permission);

        // P2-30: who stopped it, by name, for those who manage the list (a menu.view reader gets the id only)
        $names = $permission === Permission::STOPLIST_MANAGE;
        $entries = StopListEntry::where('branch_id', $branch->id)->active()->orderByDesc('id')
            ->when($names, fn ($query) => $query->with('creator:id,name,store_id'))
            ->get();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'entries' => $entries->map(fn (StopListEntry $entry) => $this->present($entry, $names))->values(),
        ]);
    }

    /**
     * Put an item on the stop-list
     *
     * `type`: product, variant or modifier of the store; `until` (optional, ISO time in the future) ends it by
     * itself. Adding an item already there updates its reason and time. Broadcasts menu.availability_changed.
     */
    public function store(Request $request, StopListService $stopList): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'type' => ['required', 'string', Rule::in(StopListSubject::values())],
            'id' => 'required|integer',
            'reason' => 'nullable|string|max:255',
            'until' => 'nullable|date',
        ]);

        $branch = $this->branch($request, Permission::STOPLIST_MANAGE);
        $type = StopListSubject::from($data['type']);
        $subject = $stopList->resolveSubject($branch, $type, (int) $data['id']);
        $until = isset($data['until']) ? Carbon::parse($data['until']) : null;

        $entry = $stopList->add($branch, $type, $subject, $data['reason'] ?? null, $until, $request->user());

        return $this->success($this->present($entry->load('creator:id,name,store_id'), true), "Stop-listga qo'shildi.", 201);
    }

    /**
     * Take an item off the stop-list
     *
     * Broadcasts menu.availability_changed.
     */
    public function destroy(Request $request, int $id, StopListService $stopList): JsonResponse
    {
        $entry = StopListEntry::where('store_id', $this->storeId($request))->find($id);
        if (!$entry) {
            return $this->error('Stop-list yozuvi topilmadi.', [], 404);
        }

        $this->authorizeFor($request, Permission::STOPLIST_MANAGE, $entry);
        $stopList->remove($entry, $request->user());

        return $this->success(null, "Stop-listdan olib tashlandi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(StopListEntry $entry, bool $withName = false): array
    {
        $creator = $withName && $entry->relationLoaded('creator') ? $entry->getRelation('creator') : null;

        $subject = $entry->subject();

        return [
            'id' => (int) $entry->id,
            'branch_id' => (int) $entry->branch_id,
            'type' => $entry->subject_type->value,
            'subject_id' => (int) $entry->subject_id,
            'subject_uuid' => $subject?->getAttribute('uuid'),
            'name' => $subject?->getAttribute('name'),
            'reason' => $entry->reason,
            'until' => $entry->until?->toJSON(),
            'created_by' => $entry->created_by !== null ? (int) $entry->created_by : null,
            // The store's own staff only (a super admin's entry shows no name)
            'created_by_name' => $creator instanceof \App\Models\User && (int) $creator->store_id === (int) $entry->store_id ? (string) $creator->name : null,
            'created_at' => $entry->created_at?->toJSON(),
        ];
    }
}
