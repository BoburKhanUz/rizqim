<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;

class TelegramBindWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $user = auth()->user();
        if (!$user->telegram_invite_token) {
            $user->update(['telegram_invite_token' => Str::uuid()->toString()]);
        }

        $botName = config('services.telegram.bot_username', 'SizningBotName');
        $link = "https://t.me/{$botName}?start=invite_{$user->telegram_invite_token}";

        $status = $user->telegram_id ? "Bog'langan (ID: {$user->telegram_id})" : "Bog'lanmagan";
        $color = $user->telegram_id ? 'success' : 'danger';

        return [
            Stat::make('Telegram Bot', $status)
                ->description('Ustiga bosib botga ulaning')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color($color)
                ->extraAttributes([
                    'onclick' => "window.open('{$link}', '_blank')",
                    'class' => 'cursor-pointer',
                ]),
        ];
    }
}
