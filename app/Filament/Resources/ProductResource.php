<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\CustomFieldFormBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $customFieldBuilder = app(CustomFieldFormBuilder::class);
        $customFields = $customFieldBuilder->buildComponents('Product');

        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(100)
                            ->unique(Product::class, 'sku', ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', app('current_company_id'));
                            })
                            ->placeholder('Enter product SKU'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter product name'),

                        Forms\Components\TextInput::make('base_uom')
                            ->label('Base UOM')
                            ->required()
                            ->default('unit')
                            ->maxLength(50)
                            ->placeholder('e.g., unit, kg, liter'),
                    ])->columns(3),

                Forms\Components\Section::make('Pricing & Stock')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('฿')
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Forms\Components\TextInput::make('cost')
                            ->numeric()
                            ->prefix('฿')
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Forms\Components\TextInput::make('stock_qty')
                            ->label('Stock Quantity')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.001)
                            ->disabled(fn(?Model $record) => $record !== null) // Only editable on create
                            ->helperText('Stock can be adjusted after creation using Stock Adjust action'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])->columns(4),

                Forms\Components\Section::make('Custom Fields')
                    ->schema($customFields)
                    ->columns(2)
                    ->visible(fn() => count($customFields) > 0)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Product $record): string => $record->base_uom),

                Tables\Columns\TextColumn::make('price')
                    ->money('THB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost')
                    ->money('THB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn(Product $record): string => $record->stock_qty <= 10 ? 'danger' : 'success')
                    ->icon(fn(Product $record): ?string => $record->stock_qty <= 10 ? 'heroicon-o-exclamation-triangle' : null)
                    ->tooltip(fn(Product $record): ?string => $record->stock_qty <= 10 ? 'Low stock alert!' : null),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock (≤ 10)')
                    ->query(fn(Builder $query): Builder => $query->where('stock_qty', '<=', 10)),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn(Builder $query): Builder => $query->where('stock_qty', '<=', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn() => auth()->user()->can('company.admin'))
                    ->form([
                        Forms\Components\TextInput::make('qty_delta')
                            ->label('Quantity Change')
                            ->required()
                            ->numeric()
                            ->helperText('Use positive for additions, negative for deductions'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Reason')
                            ->required()
                            ->rows(2)
                            ->placeholder('e.g., Stock count correction, damaged goods, etc.'),
                    ])
                    ->action(function (Product $record, array $data) {
                        $inventoryService = app(\App\Services\InventoryService::class);

                        try {
                            $inventoryService->adjust(
                                app('current_company_id'),
                                $record->id,
                                $data['qty_delta'],
                                'ADJUSTMENT',
                                null,
                                $data['notes']
                            );

                            Notification::make()
                                ->title('Stock adjusted successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to adjust stock')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_movements')
                    ->label('Stock History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn(Product $record): string => "/admin/stock-movements?tableFilters[product_id][value]={$record->id}"),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => auth()->user()->can('company.admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('company.admin')),

                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('stockMovements');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Product::class);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete', $record);
    }
}
