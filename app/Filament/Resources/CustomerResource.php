<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Support\CustomFieldFormBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $customFieldBuilder = app(CustomFieldFormBuilder::class);
        $customFields = $customFieldBuilder->buildComponents('Customer');

        $companyId = app('current_company_id');
        $tiers = CustomerTier::where('company_id', $companyId)->pluck('name', 'code')->toArray();

        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->required()
                            ->maxLength(20)
                            ->unique(Customer::class, 'phone', ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', app('current_company_id'));
                            })
                            ->tel()
                            ->placeholder('Enter phone number'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter customer name'),

                        Forms\Components\Select::make('tier_code')
                            ->label('Customer Tier')
                            ->options($tiers)
                            ->searchable()
                            ->preload()
                            ->placeholder('Select tier'),
                    ])->columns(3),

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
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tier.name')
                    ->label('Tier')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'VIP' => 'success',
                        'Regular' => 'info',
                        'Standard' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('saleOrders_count')
                    ->label('Total Orders')
                    ->counts('saleOrders')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier_code')
                    ->label('Customer Tier')
                    ->relationship('tier', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('view_orders')
                    ->label('View Orders')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->url(fn(Customer $record): string => "/admin/sale-orders?tableFilters[customer_id][value]={$record->id}"),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => auth()->user()->can('company.admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('company.admin')),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('saleOrders')->with('tier');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Customer::class);
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
