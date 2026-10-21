<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // P2-33: refused while staff, devices or zones are tied to the branch
            \App\Filament\Support\BranchDeletionGuard::action(Actions\DeleteAction::make()),
        ];
    }
}
