<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Pos\Releases\PosAppReleases;
use App\Enums\DeviceApp;
use App\Http\Requests\Admin\AdminRules;
use App\Models\PosAppRelease;
use App\Services\Admin\AdminAudit;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): the registry of POS app releases, managed by the RIZQIM team.
 * Reads: admin.devices.view. Writes: admin.stores.manage, a step-up and a `reason` (like the other POS admin writes);
 * every write is audited by its route name (admin.pos.release_created, release_updated, release_withdrawn,
 * release_restored, release_deleted) with the row's fields before and after. The public manifest is dropped from the
 * cache at once.
 */
#[Group('Admin')]
class AdminPosReleaseController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public const ROW = 'array{uuid: string, app: string, platform: string, version: string, build: int, label: string, file_url: string, sha256: string, size_bytes: int, notes: string|null, released_at: string, is_minimum: bool, minimum_from: string|null, withdrawn_at: string|null, is_latest: bool, created_at: string|null}';

    public function __construct(private readonly PosAppReleases $releases) {}

    /**
     * Releases
     *
     * Every release, withdrawn ones included (`withdrawn_at`), the newest first per app and platform. Filters: `app`
     * (cashier, waiter, kds, customer_display), `platform` (android, windows), `withdrawn=1` (only withdrawn),
     * `withdrawn=0` (only live ones). `is_latest`: the highest non-withdrawn version of its app and platform.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'app' => ['nullable', Rule::enum(DeviceApp::class)],
            'platform' => ['nullable', Rule::in(PosAppRelease::PLATFORMS)],
            'withdrawn' => 'nullable|boolean',
        ], UzValidation::messages());

        $query = PosAppRelease::query()
            ->when(filled($filters['app'] ?? null), fn ($q) => $q->where('app', $filters['app']))
            ->when(filled($filters['platform'] ?? null), fn ($q) => $q->where('platform', $filters['platform']));
        if (isset($filters['withdrawn'])) {
            filter_var($filters['withdrawn'], FILTER_VALIDATE_BOOLEAN) ? $query->whereNotNull('withdrawn_at') : $query->whereNull('withdrawn_at');
        }

        $latest = $this->latestIds();
        $rows = $query->get()
            ->sort(fn (PosAppRelease $a, PosAppRelease $b) => [$a->app, $a->platform] <=> [$b->app, $b->platform]
                ?: version_compare($b->version, $a->version) ?: $b->build <=> $a->build)
            ->map(fn (PosAppRelease $release) => $this->row($release, $latest))
            ->values()->all();

        return $this->success(['data' => $rows]);
    }

    /**
     * A release
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such release (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $release = $this->find($uuid);

        return $release === null ? $this->notFound() : $this->success($this->row($release, $this->latestIds()));
    }

    /**
     * Add a release
     *
     * `app` (cashier, waiter, kds, customer_display), `platform` (android, windows), `version` (1.4.0), `build`
     * (default 0), `file_url` (https), `sha256` (64 hex), `size_bytes`, `notes` (Uzbek, shown in the app), `released_at`
     * (default now), `is_minimum` (versions below this one are unsupported), `minimum_from` (the day the notice turns
     * red; with `is_minimum` and none given: 14 days from now), `reason`. The same app, platform, version and build
     * twice: 409 `release_exists`. Needs a step-up. Answers the release (201).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(409, 'The release already exists (release_exists)', type: self::ERROR)]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app' => ['required', Rule::enum(DeviceApp::class)],
            'platform' => ['required', Rule::in(PosAppRelease::PLATFORMS)],
            'version' => ['required', 'string', 'max:32', 'regex:/^\d{1,6}(\.\d{1,6}){1,3}$/'],
            'build' => 'nullable|integer|min:0|max:999999999',
            'file_url' => ['required', 'string', 'max:500', 'url:https'],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'size_bytes' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:2000',
            'released_at' => 'nullable|date',
            'is_minimum' => 'nullable|boolean',
            'minimum_from' => 'nullable|date',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $build = (int) ($data['build'] ?? 0);
        $exists = PosAppRelease::query()->where(['app' => $data['app'], 'platform' => $data['platform'], 'version' => $data['version'], 'build' => $build])->exists();
        if ($exists) {
            return $this->refuse("Bu versiya allaqachon qo'shilgan.", 'release_exists', 409);
        }

        $minimum = (bool) ($data['is_minimum'] ?? false);
        // Review 3 LOW: two staff adding the same build at once both pass the check above; the unique index refuses
        // the second, answered like the plain duplicate (in its own transaction, so the connection stays usable)
        try {
            $release = DB::transaction(fn () => PosAppRelease::create([
                'app' => $data['app'],
                'platform' => $data['platform'],
                'version' => $data['version'],
                'build' => $build,
                'file_url' => $data['file_url'],
                'sha256' => strtolower($data['sha256']),
                'size_bytes' => (int) $data['size_bytes'],
                'notes' => $data['notes'] ?? null,
                'released_at' => isset($data['released_at']) ? Carbon::parse($data['released_at']) : now(),
                'is_minimum' => $minimum,
                'minimum_from' => $minimum ? (isset($data['minimum_from']) ? Carbon::parse($data['minimum_from']) : now()->addDays(PosAppReleases::graceDays())) : null,
                'created_by' => $this->member($request)->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            return $this->refuse("Bu versiya allaqachon qo'shilgan.", 'release_exists', 409);
        }
        PosAppReleases::forget();

        AdminAudit::for($request)->subject($release)->changes([], $this->snapshot($release))->meta($this->identity($release));

        return $this->success($this->row($release->refresh(), $this->latestIds()), "Versiya qo'shildi.", 201);
    }

    /**
     * Change a release
     *
     * `notes`, `file_url`, `sha256`, `size_bytes`, `released_at`, `is_minimum`, `minimum_from` and the `reason`; the
     * app, platform, version and build cannot change (withdraw or delete the row and add another). Declaring a minimum
     * without `minimum_from` sets it 14 days ahead; clearing `is_minimum` clears `minimum_from`. Needs a step-up.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such release (not_found)', type: self::ERROR)]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'file_url' => ['sometimes', 'required', 'string', 'max:500', 'url:https'],
            'sha256' => ['sometimes', 'required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'size_bytes' => 'sometimes|required|integer|min:0',
            'notes' => 'sometimes|nullable|string|max:2000',
            'released_at' => 'sometimes|required|date',
            'is_minimum' => 'sometimes|required|boolean',
            'minimum_from' => 'sometimes|nullable|date',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());
        $release = $this->find($uuid);
        if ($release === null) {
            return $this->notFound();
        }

        $before = $this->snapshot($release);
        $fill = collect($data)->only(['file_url', 'sha256', 'size_bytes', 'notes', 'released_at'])->all();
        if (isset($fill['sha256'])) {
            $fill['sha256'] = strtolower($fill['sha256']);
        }
        if (isset($fill['released_at'])) {
            $fill['released_at'] = Carbon::parse($fill['released_at']);
        }
        $minimum = array_key_exists('is_minimum', $data) ? (bool) $data['is_minimum'] : $release->is_minimum;
        if (!$minimum) {
            $fill += ['is_minimum' => false, 'minimum_from' => null];
        } else {
            $from = array_key_exists('minimum_from', $data) && $data['minimum_from'] !== null
                ? Carbon::parse($data['minimum_from'])
                : ($release->is_minimum ? $release->minimum_from : null);
            $fill += ['is_minimum' => true, 'minimum_from' => $from ?? now()->addDays(PosAppReleases::graceDays())];
        }
        $release->forceFill($fill)->save();
        PosAppReleases::forget();

        AdminAudit::for($request)->subject($release)->changes($before, $this->snapshot($release))->meta($this->identity($release));

        return $this->success($this->row($release->refresh(), $this->latestIds()), 'Versiya yangilandi.');
    }

    /**
     * Withdraw a release
     *
     * `reason`. A withdrawn release is no longer "latest" or "minimum" and leaves the public manifest; tills that run
     * it are not touched. Withdrawing a withdrawn release changes nothing. Needs a step-up. Answers the release.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such release (not_found)', type: self::ERROR)]
    public function withdraw(Request $request, string $uuid): JsonResponse
    {
        return $this->setWithdrawn($request, $uuid, true);
    }

    /**
     * Bring a withdrawn release back
     *
     * `reason`. Undoes a withdrawal. Needs a step-up. Answers the release.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such release (not_found)', type: self::ERROR)]
    public function restore(Request $request, string $uuid): JsonResponse
    {
        return $this->setWithdrawn($request, $uuid, false);
    }

    /**
     * Delete a release
     *
     * `reason`. For a row added by mistake: it goes for good, the audit entry keeps its fields. A release that was
     * published should be withdrawn instead. Needs a step-up. Answers `{uuid}`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{uuid: string}}')]
    #[ApiDocResponse(404, 'No such release (not_found)', type: self::ERROR)]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $release = $this->find($uuid);
        if ($release === null) {
            return $this->notFound();
        }

        $before = $this->snapshot($release);
        $release->delete();
        PosAppReleases::forget();
        AdminAudit::for($request)->subject($release)->changes($before, [])->meta($this->identity($release));

        return $this->success(['uuid' => strtolower($uuid)], "Versiya o'chirildi.");
    }

    private function setWithdrawn(Request $request, string $uuid, bool $withdraw): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $release = $this->find($uuid);
        if ($release === null) {
            return $this->notFound();
        }

        $already = ($release->withdrawn_at !== null) === $withdraw;
        $before = $this->snapshot($release);
        if (!$already) {
            $release->forceFill(['withdrawn_at' => $withdraw ? now() : null])->save();
            PosAppReleases::forget();
        }

        $audit = AdminAudit::for($request)->subject($release)->changes($before, $this->snapshot($release))->meta($this->identity($release));
        if ($already) {
            $audit->skip();
        }

        return $this->success($this->row($release->refresh(), $this->latestIds()), $withdraw ? 'Versiya olib tashlandi.' : 'Versiya qaytarildi.');
    }

    private function find(string $uuid): ?PosAppRelease
    {
        return PosAppRelease::query()->where('uuid', strtolower($uuid))->first();
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Versiya topilmadi.', 'not_found', 404);
    }

    /**
     * @return array<int, true> the ids of the latest non-withdrawn release per app and platform
     */
    private function latestIds(): array
    {
        $ids = [];
        foreach ($this->releases->latestRows() as $release) {
            $ids[$release->id] = true;
        }

        return $ids;
    }

    /**
     * @param  array<int, true>  $latest
     * @return array<string, mixed>
     */
    private function row(PosAppRelease $release, array $latest): array
    {
        return [
            'uuid' => (string) $release->uuid,
            'app' => (string) $release->app,
            'platform' => (string) $release->platform,
            'version' => (string) $release->version,
            'build' => (int) $release->build,
            'label' => $release->label(),
            'file_url' => (string) $release->file_url,
            'sha256' => (string) $release->sha256,
            'size_bytes' => (int) $release->size_bytes,
            'notes' => $release->notes,
            'released_at' => $release->released_at->toIso8601String(),
            'is_minimum' => (bool) $release->is_minimum,
            'minimum_from' => $release->minimum_from?->toIso8601String(),
            'withdrawn_at' => $release->withdrawn_at?->toIso8601String(),
            'is_latest' => isset($latest[$release->id]),
            'created_at' => $release->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed> what the audit entry keeps as the row's before/after
     */
    private function snapshot(PosAppRelease $release): array
    {
        return [
            'file_url' => $release->file_url,
            'sha256' => $release->sha256,
            'size_bytes' => (int) $release->size_bytes,
            'notes' => $release->notes,
            'released_at' => $release->released_at->toIso8601String(),
            'is_minimum' => (bool) $release->is_minimum,
            'minimum_from' => $release->minimum_from?->toIso8601String(),
            'withdrawn_at' => $release->withdrawn_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{app: string, platform: string, version: string, build: int, release: string}
     */
    private function identity(PosAppRelease $release): array
    {
        return [
            'app' => (string) $release->app,
            'platform' => (string) $release->platform,
            'version' => (string) $release->version,
            'build' => (int) $release->build,
            'release' => (string) $release->uuid,
        ];
    }
}
