<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock Alert (≤ 10 units)')
            ->query(
                Product::query()
                    ->where('stock_qty', '<=', 10)
                    ->where('is_active', true)
                    ->orderBy('stock_qty')
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn($state) => $state <= 0 ? 'danger' : 'warning')
                    ->icon(fn($state) => $state <= 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle')
                    ->suffix(fn(Product $record) => ' ' . $record->base_uom),

                Tables\Columns\TextColumn::make('price')
                    ->money('THB'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Product $record): string => "/admin/products/{$record->id}/edit"),
            ])
            ->emptyStateHeading('No Low Stock Products')
            ->emptyStateDescription('All products have sufficient stock levels')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
