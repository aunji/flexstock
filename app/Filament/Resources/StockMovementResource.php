<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Stock Movements';

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Read-only, no forms
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('Product SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('ref_type')
                    ->label('Reference Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'SALE' => 'danger',
                        'ADJUSTMENT' => 'warning',
                        'OPENING' => 'success',
                        'RETURN' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ref_id')
                    ->label('Reference ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty_in')
                    ->label('Qty In')
                    ->numeric(decimalPlaces: 2)
                    ->color('success')
                    ->default('-'),

                Tables\Columns\TextColumn::make('qty_out')
                    ->label('Qty Out')
                    ->numeric(decimalPlaces: 2)
                    ->color('danger')
                    ->default('-'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('ref_type')
                    ->label('Reference Type')
                    ->options([
                        'SALE' => 'Sale',
                        'ADJUSTMENT' => 'Adjustment',
                        'OPENING' => 'Opening',
                        'RETURN' => 'Return',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                // Read-only, no actions
            ])
            ->bulkActions([
                // Read-only, no bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Read-only
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product']);
    }
}
