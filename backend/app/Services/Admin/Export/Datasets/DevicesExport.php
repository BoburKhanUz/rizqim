<?php

namespace App\Services\Admin\Export\Datasets;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Pos\PosDevices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * P4-31: POS va obunalar → Qurilmalar → Excel. The list's filters (state, type, terminal, store, outdated, q) through
 * the list's own query and rows (PosDevices): online / offline, app version and the newest version seen.
 */
final class DevicesExport extends ExportDataset
{
    private Carbon $now;

    /** @var array<string, string> */
    private array $latest = [];

    public function __construct(
        private readonly PosDevices $devices,
    ) {
        $this->now = now();
    }

    public function key(): string
    {
        return 'pos_devices';
    }

    public function sheet(): string
    {
        return 'Qurilmalar';
    }

    public function label(): string
    {
        return 'POS: qurilmalar';
    }

    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::in(['online', 'offline', 'never', 'revoked', 'all'])],
            'type' => ['nullable', Rule::enum(DeviceType::class)],
            'terminal' => 'nullable|boolean',
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'outdated' => 'nullable|boolean',
            'q' => 'nullable|string|min:2|max:100',
        ];
    }

    public function headers(): array
    {
        return ['Qurilma', 'Turi', 'Ovqatxona', 'Filial', 'Platforma', 'Ilova versiyasi', 'Eng yangi versiya', 'Eskirgan', 'Holat', 'Oxirgi aloqa', 'Ulangan', 'Bekor qilingan', 'Yuborilmagan amallar', 'UUID'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $this->latest = $this->devices->latestVersions();

        return $this->devices->withRelations($this->devices->query($filters, $this->now));
    }

    public function keyColumn(): string
    {
        return 'devices.id';
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var Device $device */
        $device = $record;
        $row = $this->devices->row($device, $this->now, $this->latest);

        return [
            $cells->text($row['name']),
            $row['type_label'],
            $cells->text($row['store']['name'] ?? null),
            $cells->text($row['branch']['name'] ?? null),
            $cells->text($row['platform']),
            $cells->text($row['app_version']),
            $cells->text($row['latest_version']),
            $cells->yesNo($row['outdated']),
            match ($row['state']) {
                'online' => 'Onlayn',
                'offline' => 'Oflayn',
                'never' => "Hech ulanmagan",
                'revoked' => 'Bekor qilingan',
                default => $row['state'],
            },
            $cells->time($device->last_seen_at),
            $cells->time($device->created_at),
            $cells->time($device->revoked_at),
            $cells->number($row['sync']['pending_ops']),
            $cells->text($row['uuid']),
        ];
    }
}
