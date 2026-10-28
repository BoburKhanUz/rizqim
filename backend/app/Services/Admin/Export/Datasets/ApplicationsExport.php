<?php

namespace App\Services\Admin\Export\Datasets;

use App\Enums\StoreApplicationStage;
use App\Models\StoreApplication;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Stores\ApplicationPipeline;
use App\Services\Admin\Stores\StorePresenter;
use App\Services\Admin\Today\PlatformStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * P4-31: Ovqatxonalar → Ulash jarayoni → Excel. The pipeline list's filters (stage — also `open` and `overdue` —,
 * assignee, source, q) and its rows (StorePresenter::applicationRow), plus the applicant's phone and STIR (phone masked
 * unless admin.pii.view).
 */
final class ApplicationsExport extends ExportDataset
{
    public function __construct(
        private readonly StorePresenter $presenter,
    ) {}

    public function key(): string
    {
        return 'store_applications';
    }

    public function sheet(): string
    {
        return 'Arizalar';
    }

    public function label(): string
    {
        return "Ovqatxona arizalari (ulash jarayoni)";
    }

    public function rules(): array
    {
        return [
            'stage' => ['nullable', Rule::in([...StoreApplicationStage::values(), 'open', 'overdue'])],
            'assignee_id' => ['nullable', 'regex:/^(me|\d{1,18})$/'],
            'source' => ['nullable', Rule::in(StoreApplication::SOURCES)],
            'q' => 'nullable|string|min:2|max:100',
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Nomi', 'Format', 'Viloyat', 'Shahar', 'Egasi', 'Telefon', 'STIR', 'Manba', 'Bosqich', "Bosqichga o'tgan", "Mas'ul", 'Muddat', 'Kechikkan', 'Ovqatxona', 'Yaratilgan'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');
        $open = fn (Builder $query) => $query->whereNotIn('stage', [StoreApplicationStage::ACTIVE->value, StoreApplicationStage::REJECTED->value]);

        $query = StoreApplication::query();
        if (filled($filters['assignee_id'] ?? null)) {
            $query->where('assignee_id', $filters['assignee_id'] === 'me' ? $viewer->id : (int) $filters['assignee_id']);
        }
        if (filled($filters['source'] ?? null)) {
            $query->where('source', $filters['source']);
        }
        if (filled($filters['q'] ?? null)) {
            $q = trim((string) $filters['q']);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $phone = ApplicationPipeline::normalizePhone($q);
            $query->where(fn (Builder $where) => $where->where('name', 'ilike', $like)->orWhere('owner_name', 'ilike', $like)
                ->when($phone !== null, fn (Builder $byPhone) => $byPhone->orWhere('phone', $phone)));
        }

        $stage = $filters['stage'] ?? null;
        if ($stage === 'open') {
            $open($query);
        } elseif ($stage === 'overdue') {
            $open($query)->where('due_on', '<', $today);
        } elseif ($stage !== null) {
            $query->where('stage', $stage);
        }

        return $query;
    }

    public function keyColumn(): string
    {
        return 'store_applications.id';
    }

    public function with(): array
    {
        return ['region:id,name_uz', 'assignee:id,name', 'store:id,name,status'];
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var StoreApplication $application */
        $application = $record;
        $row = $this->presenter->applicationRow($application);

        return [
            (int) $application->id,
            $cells->text($application->name),
            $row['format_label'],
            $cells->text($row['region']['name'] ?? null),
            $cells->text($application->city),
            $cells->text($application->owner_name),
            $cells->phone($application->phone),
            $cells->text($application->tin),
            $cells->text($application->source),
            $row['stage_label'],
            $cells->time($application->stage_changed_at),
            $cells->text($row['assignee']['name'] ?? null),
            $cells->text($row['due_on']),
            $cells->yesNo($row['overdue']),
            $cells->text($row['store']['name'] ?? null),
            $cells->time($application->created_at),
        ];
    }
}
