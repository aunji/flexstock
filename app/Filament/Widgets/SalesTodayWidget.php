<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesTodayWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $reportService = app(ReportService::class);

        // Get today's sales
        $today = now()->startOfDay()->toDateString();
        $summary = $reportService->getSalesSummary($today, $today);

        // Get payment method breakdown
        $cashCount = $summary['payment_methods']['cash']['count'] ?? 0;
        $transferCount = $summary['payment_methods']['transfer']['count'] ?? 0;

        return [
            Stat::make('Today\'s Revenue', '฿' . number_format($summary['total_revenue'], 2))
                ->description($summary['total_orders'] . ' orders completed')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Cash Payments', $cashCount)
                ->description('Cash transactions today')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Transfer Payments', $transferCount)
                ->description('Bank transfers today')
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('info'),
        ];
    }
}
