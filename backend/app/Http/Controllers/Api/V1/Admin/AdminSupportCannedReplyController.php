<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\SupportCannedReply;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Customers\CustomerFormat;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-31: Shikoyatlar → Tayyor javoblar: texts the support team starts a reply from (admin.support.view reads them,
 * admin.support.manage writes them; audited). A reply keeps its own copy of the text.
 */
#[Group('Admin')]
class AdminSupportCannedReplyController extends AdminController
{
    private const MAX = 200;

    private const ITEM = 'array{id: int, title: string, body: string, sort_order: int, updated_by: array{id: int, name: string}|null, updated_at: string|null}';

    /**
     * Tayyor javoblar
     *
     * Every one, in `sort_order` then creation order.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ITEM . '>}')]
    public function index(): JsonResponse
    {
        return $this->success(SupportCannedReply::query()->with('updater:id,name')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (SupportCannedReply $reply) => $this->present($reply))->values()->all());
    }

    /**
     * Add a tayyor javob
     *
     * `title` (1–120), `body` (1–1000, plain text), `sort_order` (0–1000, optional). At most 200; 422 `limit_reached`.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ITEM . '}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true), UzValidation::messages());
        if (SupportCannedReply::query()->count() >= self::MAX) {
            return $this->refuse("Tayyor javoblar ko'pi bilan " . self::MAX . ' ta bo\'ladi.', 'limit_reached', 422);
        }

        $member = $this->member($request);
        $reply = SupportCannedReply::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $member->id,
            'updated_by' => $member->id,
        ]);
        AdminAudit::for($request)->subject($reply)->changes([], ['title' => $reply->title]);

        return $this->success($this->present($reply->load('updater:id,name')), "Tayyor javob qo'shildi.", 201);
    }

    /**
     * Change a tayyor javob
     *
     * Any of `title`, `body`, `sort_order`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ITEM . '}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rules(false), UzValidation::messages());
        $reply = SupportCannedReply::query()->find($id);
        if ($reply === null) {
            return $this->refuse('Tayyor javob topilmadi.', 'not_found', 404);
        }

        $before = $reply->only(['title', 'body', 'sort_order']);
        $reply->fill(array_map(fn ($value) => is_string($value) ? trim($value) : (int) $value, array_intersect_key($data, array_flip(['title', 'body', 'sort_order']))));
        $reply->updated_by = $this->member($request)->id;
        $after = $reply->only(['title', 'body', 'sort_order']);
        $reply->save();

        $audit = AdminAudit::for($request)->subject($reply)->changes(array_diff_assoc($before, $after), array_diff_assoc($after, $before));
        if ($before === $after) {
            $audit->skip();
        }

        return $this->success($this->present($reply->load('updater:id,name')), 'Tayyor javob saqlandi.');
    }

    /**
     * Delete a tayyor javob
     *
     * Replies sent from it keep their text.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{deleted: bool}}')]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $reply = SupportCannedReply::query()->find($id);
        if ($reply === null) {
            return $this->refuse('Tayyor javob topilmadi.', 'not_found', 404);
        }

        AdminAudit::for($request)->subject($reply)->changes(['title' => $reply->title], []);
        $reply->delete();

        return $this->success(['deleted' => true], "Tayyor javob o'chirildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes|required';

        return [
            'title' => "{$required}|string|min:1|max:120",
            'body' => "{$required}|string|min:1|max:1000",
            'sort_order' => 'sometimes|integer|min:0|max:1000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SupportCannedReply $reply): array
    {
        return [
            'id' => (int) $reply->id,
            'title' => (string) $reply->title,
            'body' => (string) $reply->body,
            'sort_order' => (int) $reply->sort_order,
            'updated_by' => $reply->updater ? ['id' => (int) $reply->updater->id, 'name' => (string) $reply->updater->name] : null,
            'updated_at' => CustomerFormat::iso($reply->updated_at),
        ];
    }
}
