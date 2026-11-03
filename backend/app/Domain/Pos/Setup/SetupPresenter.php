<?php

namespace App\Domain\Pos\Setup;

use App\Models\KitchenStation;
use App\Models\Printer;

/**
 * The shapes of kitchen stations and printers in the management API and in GET /pos/bootstrap. A device is shown by
 * its public uuid (its numeric id is never exposed).
 */
final class SetupPresenter
{
    /**
     * @return array{id: int, branch_id: int, name: string, kind: string, kind_label: string, sort_order: int, is_active: bool, show_on_kds: bool, target_seconds: int|null}
     */
    public static function station(KitchenStation $station): array
    {
        return [
            'id' => (int) $station->id,
            'branch_id' => (int) $station->branch_id,
            'name' => (string) $station->name,
            'kind' => $station->kind->value,
            'kind_label' => $station->kind->label(),
            'sort_order' => (int) $station->sort_order,
            'is_active' => (bool) $station->is_active,
            'show_on_kds' => (bool) $station->show_on_kds,
            // P6-33: the line time target (an expo station: the assembly target); null: none
            'target_seconds' => $station->target_seconds !== null ? (int) $station->target_seconds : null,
        ];
    }

    /**
     * @return array{id: int, branch_id: int, name: string, purpose: string, connection: string, address: string|null, port: int|null, paper_width: int, station_id: int|null, device_id: string|null, is_active: bool, encoding: string|null, print_as_image: bool}
     */
    public static function printer(Printer $printer): array
    {
        return [
            'id' => (int) $printer->id,
            'branch_id' => (int) $printer->branch_id,
            'name' => (string) $printer->name,
            'purpose' => $printer->purpose->value,
            'connection' => $printer->connection->value,
            'address' => $printer->address,
            'port' => $printer->port !== null ? (int) $printer->port : null,
            'paper_width' => (int) $printer->paper_width,
            'station_id' => $printer->station_id !== null ? (int) $printer->station_id : null,
            'device_id' => $printer->device?->uuid,
            'is_active' => (bool) $printer->is_active,
            'encoding' => $printer->encoding,
            'print_as_image' => (bool) $printer->print_as_image,
        ];
    }
}
