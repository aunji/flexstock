<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\ChartWidget;

class PaymentMixWidget extends ChartWidget
{
    protected static ?string $heading = 'Payment Mix (Last 30 Days)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $reportService = app(ReportService::class);

        // Get last 30 days sales
        $startDate = now()->subDays(30)->toDateString();
        $summary = $reportService->getSalesSummary($startDate);

        $cashTotal = $summary['payment_methods']['cash']['total'] ?? 0;
        $transferTotal = $summary['payment_methods']['transfer']['total'] ?? 0;

        return [
            'datasets' => [
                [
                    'label' => 'Payment Methods',
                    'data' => [$cashTotal, $transferTotal],
                    'backgroundColor' => [
                        'rgb(255, 205, 86)',  // Yellow for cash
                        'rgb(54, 162, 235)',  // Blue for transfer
                    ],
                ],
            ],
            'labels' => ['Cash', 'Bank Transfer'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }
}
