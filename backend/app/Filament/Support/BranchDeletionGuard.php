<?php

namespace App\Filament\Support;

use App\Models\Branch;
use App\Services\Store\BranchService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * P2-33: the panels' branch delete actions (the store panel's BranchResource, the superadmin's branches tab) refuse,
 * like DELETE /owner/branches/{id}, while staff, unrevoked devices or delivery zones are tied to the branch
 * (BranchService::referencesRefusal): the foreign keys would set their branch to NULL, which means "the whole store".
 * The panels keep deleting a branch that has orders (docs/API.md, "Faqat Filament'da qoladiganlar").
 */
final class BranchDeletionGuard
{
    public static function action(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Branch $record): void {
            $refusal = app(BranchService::class)->referencesRefusal($record);
            if ($refusal !== null) {
                self::notify($refusal);
                $action->cancel();
            }
        });
    }

    public static function bulk(DeleteBulkAction $action): DeleteBulkAction
    {
        return $action->before(function (DeleteBulkAction $action, Collection $records): void {
            foreach ($records as $record) {
                $refusal = $record instanceof Branch ? app(BranchService::class)->referencesRefusal($record) : null;
                if ($refusal !== null) {
                    self::notify("{$record->name}: {$refusal}");
                    $action->cancel();
                }
            }
        });
    }

    private static function notify(string $message): void
    {
        Notification::make()
            ->danger()
            ->title("Filialni o'chirib bo'lmaydi")
            ->body($message)
            ->persistent()
            ->send();
    }
}
