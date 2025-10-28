<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProductsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $reportService = app(ReportService::class);

        // Get top products for last 30 days
        $startDate = now()->subDays(30)->toDateString();
        $topProducts = $reportService->getTopProducts(10, $startDate);

        return $table
            ->heading('Top 10 Products (Last 30 Days)')
            ->query(
                \App\Models\Product::query()
                    ->whereIn('id', collect($topProducts)->pluck('id'))
                    ->orderByRaw('FIELD(id, ' . collect($topProducts)->pluck('id')->implode(',') . ')')
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qty Sold')
                    ->getStateUsing(function ($record) use ($topProducts) {
                        $product = collect($topProducts)->firstWhere('id', $record->id);
                        return number_format($product['total_qty'] ?? 0, 2);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->getStateUsing(function ($record) use ($topProducts) {
                        $product = collect($topProducts)->firstWhere('id', $record->id);
                        return '฿' . number_format($product['total_revenue'] ?? 0, 2);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->getStateUsing(function ($record) use ($topProducts) {
                        $product = collect($topProducts)->firstWhere('id', $record->id);
                        return $product['order_count'] ?? 0;
                    })
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
