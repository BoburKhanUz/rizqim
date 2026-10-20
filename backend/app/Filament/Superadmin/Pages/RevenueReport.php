<?php

namespace App\Filament\Superadmin\Pages;

use App\Services\Finance\RevenueReportService;
use App\Support\Money;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * P1-23: Rizqim's income by month, commission vs subscription, and per store for one month (docs/BILLING.md).
 */
class RevenueReport extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static \UnitEnum|string|null $navigationGroup = 'Tariflar';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Rizqim daromadi';
    protected static ?string $title = 'Rizqim daromadi';
    protected static ?string $slug = 'revenue-report';
    protected string $view = 'filament.superadmin.pages.revenue-report';

    public string $from = '';

    public string $to = '';

    public string $period = '';

    public function mount(): void
    {
        $now = Carbon::now((string) config('billing.timezone', 'Asia/Tashkent'));
        $this->to = $now->format('Y-m');
        $this->from = $now->copy()->startOfMonth()->subMonthsNoOverflow(11)->format('Y-m');
        $this->period = $now->copy()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
    }

    /**
     * The last 36 months, newest first, for the pickers.
     *
     * @return array<string, string>
     */
    public function monthOptions(): array
    {
        $month = Carbon::now((string) config('billing.timezone', 'Asia/Tashkent'))->startOfMonth();
        $options = [];
        for ($i = 0; $i < 36; $i++) {
            $options[$month->format('Y-m')] = $month->format('Y-m');
            $month->subMonthNoOverflow();
        }

        return $options;
    }

    /**
     * @return list<array{period: string, commission: Money, subscription: Money, total: Money}>
     */
    public function monthlyRows(): array
    {
        [$from, $to] = $this->valid($this->from) && $this->valid($this->to) && $this->from <= $this->to
            ? [$this->from, $this->to]
            : [array_key_last($this->monthOptions()), array_key_first($this->monthOptions())];

        return app(RevenueReportService::class)->monthly((string) $from, (string) $to);
    }

    /**
     * @return list<array{store_id: int, store_name: string, commission: Money, subscription: Money, total: Money}>
     */
    public function storeRows(): array
    {
        return $this->valid($this->period) ? app(RevenueReportService::class)->byStore($this->period) : [];
    }

    public function uzs(Money $amount): string
    {
        return number_format($amount->toFloat(), 0, '.', ' ') . ' UZS';
    }

    private function valid(string $month): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }
}
