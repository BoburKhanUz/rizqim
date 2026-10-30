<?php

namespace App\Domain\Pos\Labels;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-32: the `labels` section of GET /pos/bootstrap: the branch's sticker and label settings (LabelSettings::view: size,
 * which items get a cup sticker, one per piece, bag label) for the till to print from; null while the branch does not
 * have the `labels` module (nothing prints a sticker there).
 */
final class LabelsBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = LabelSettings::VIEW . '|null';

    public function __construct(
        private readonly LabelSettings $settings,
        private readonly PosModules $modules,
    ) {}

    /**
     * @return array{labels: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        return ['labels' => $this->modules->enabled($branch, PosModule::LABELS) ? $this->settings->view($branch) : null];
    }
}
