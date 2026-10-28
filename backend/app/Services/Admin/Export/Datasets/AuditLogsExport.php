<?php

namespace App\Services\Admin\Export\Datasets;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * P4-31: Tizim → Harakatlar jurnali → Excel. The log's filters (actor, action prefix, store, subject, from/to, q) as the
 * log endpoint reads them. What the log holds is already free of secrets and with phone numbers masked (AuditRedactor);
 * details are written as JSON text.
 */
final class AuditLogsExport extends ExportDataset
{
    public function key(): string
    {
        return 'audit_logs';
    }

    public function sheet(): string
    {
        return 'Jurnal';
    }

    public function label(): string
    {
        return 'Harakatlar jurnali';
    }

    public function rules(): array
    {
        $id = 'nullable|integer|min:1|max:999999999999999999';

        return [
            'actor_id' => $id,
            'action' => 'nullable|string|max:64',
            'store_id' => $id,
            'subject_type' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z]+$/'],
            'subject_id' => $id,
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|min:2|max:100',
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Vaqt', 'Harakat', 'Kim', 'Roli', 'Ovqatxona', 'Obyekt turi', 'Obyekt ID', 'Sabab', 'IP', 'Oldin', 'Keyin', 'Tafsilot'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $like = fn (string $value) => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);

        return AuditLog::query()
            ->when(filled($filters['actor_id'] ?? null), fn (Builder $query) => $query->where('user_id', (int) $filters['actor_id']))
            ->when(filled($filters['action'] ?? null), function (Builder $query) use ($filters, $like) {
                $prefix = (string) $filters['action'];
                $prefix = str_ends_with($prefix, '.*') ? substr($prefix, 0, -1) : $prefix;
                $query->where('action', 'like', $like($prefix) . '%');
            })
            ->when(filled($filters['store_id'] ?? null), fn (Builder $query) => $query->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['subject_type'] ?? null), fn (Builder $query) => $query->where('model_type', 'App\\Models\\' . ucfirst((string) $filters['subject_type'])))
            ->when(filled($filters['subject_id'] ?? null), fn (Builder $query) => $query->where('model_id', (int) $filters['subject_id']))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay()))
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters, $like) {
                $text = '%' . $like(mb_strtolower((string) $filters['q'])) . '%';
                $query->where(fn (Builder $q) => $q
                    ->whereRaw('lower(action) like ?', [$text])
                    ->orWhereRaw('lower(cast(payload_json as text)) like ?', [$text])
                    ->orWhereRaw('lower(cast("before" as text)) like ?', [$text])
                    ->orWhereRaw('lower(cast("after" as text)) like ?', [$text]));
            });
    }

    public function keyColumn(): string
    {
        return 'audit_logs.id';
    }

    public function with(): array
    {
        return ['user:id,name,role,platform_role', 'store:id,name'];
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var AuditLog $log */
        $log = $record;
        $meta = $log->payload_json;

        return [
            (int) $log->id,
            $cells->time($log->created_at),
            $cells->text($log->action),
            $cells->text($log->user?->name),
            $cells->text($log->user?->platform_role->value ?? $log->user?->role->value),
            $cells->text($log->store?->name),
            $cells->text($log->subjectName()),
            $cells->number($log->model_id),
            $cells->text(is_array($meta) && is_string($meta['reason'] ?? null) ? $meta['reason'] : null),
            $cells->text($log->ip),
            $cells->text($log->before),
            $cells->text($log->after),
            $cells->text($meta),
        ];
    }
}
