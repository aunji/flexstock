<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Services\SaleOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Sale Orders';

    public static function form(Form $form): Form
    {
        $companyId = app('current_company_id');

        return $form
            ->schema([
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship('customer', 'name', function (Builder $query) use ($companyId) {
                                $query->where('company_id', $companyId);
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->required()
                                    ->tel(),
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data) use ($companyId) {
                                $data['company_id'] = $companyId;
                                return Customer::create($data)->id;
                            }),

                        Forms\Components\Placeholder::make('tx_id_info')
                            ->label('Transaction ID')
                            ->content('Will be generated automatically')
                            ->visible(fn($record) => $record === null),

                        Forms\Components\TextInput::make('tx_id')
                            ->label('Transaction ID')
                            ->disabled()
                            ->visible(fn($record) => $record !== null),
                    ])->columns(2),

                Forms\Components\Section::make('Order Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(function () use ($companyId) {
                                        return Product::where('company_id', $companyId)
                                            ->where('is_active', true)
                                            ->get()
                                            ->pluck('name', 'id');
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_price', $product->price);
                                                $set('uom', $product->base_uom);
                                            }
                                        }
                                    }),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->reactive(),

                                Forms\Components\TextInput::make('uom')
                                    ->label('UOM')
                                    ->default('unit')
                                    ->required(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('฿')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->reactive(),

                                Forms\Components\TextInput::make('discount_value')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix('฿')
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(0.01),

                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('Tax (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%'),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->cloneable()
                            ->reorderable(false)
                            ->required()
                            ->minItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tx_id')
                    ->label('TX ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(SaleOrder $record): string => $record->customer->phone ?? ''),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Confirmed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_state')
                    ->label('Payment')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Received' => 'success',
                        'PendingReceipt' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('THB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Confirmed' => 'Confirmed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('payment_state')
                    ->label('Payment Status')
                    ->options([
                        'PendingReceipt' => 'Pending Receipt',
                        'Received' => 'Received',
                    ]),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(SaleOrder $record) => $record->status === 'Draft'),

                Tables\Actions\Action::make('confirm')
                    ->label('Confirm Order')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(SaleOrder $record) => $record->status === 'Draft')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Sale Order')
                    ->modalDescription('This will deduct stock and confirm the order. Are you sure?')
                    ->action(function (SaleOrder $record) {
                        try {
                            $service = app(SaleOrderService::class);
                            $service->confirm($record, auth()->id());

                            Notification::make()
                                ->title('Order confirmed successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to confirm order')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('mark_payment')
                    ->label('Mark Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->visible(fn(SaleOrder $record) => $record->status === 'Confirmed' && $record->payment_state === 'PendingReceipt')
                    ->form([
                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->required()
                            ->options([
                                'cash' => 'Cash',
                                'transfer' => 'Bank Transfer',
                            ])
                            ->reactive(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->placeholder('Optional payment notes'),

                        Forms\Components\FileUpload::make('slip_file')
                            ->label('Payment Slip')
                            ->image()
                            ->maxSize(5120) // 5MB
                            ->visible(fn($get) => $get('payment_method') === 'transfer')
                            ->required(fn($get) => $get('payment_method') === 'transfer')
                            ->helperText('Required for bank transfers'),
                    ])
                    ->action(function (SaleOrder $record, array $data) {
                        try {
                            $service = app(SaleOrderService::class);
                            $service->markPaymentReceived(
                                $record,
                                $data['payment_method'],
                                $data['notes'] ?? null
                            );

                            // If transfer with slip, create PaymentSlip record
                            if ($data['payment_method'] === 'transfer' && isset($data['slip_file'])) {
                                \App\Models\PaymentSlip::create([
                                    'company_id' => $record->company_id,
                                    'sale_order_id' => $record->id,
                                    'slip_path' => $data['slip_file'],
                                    'status' => 'Pending',
                                    'notes' => $data['notes'] ?? null,
                                    'uploaded_by' => auth()->id(),
                                ]);
                            }

                            Notification::make()
                                ->title('Payment recorded successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to record payment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(SaleOrder $record) => $record->status !== 'Cancelled')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Sale Order')
                    ->modalDescription('This will cancel the order and restore stock if it was confirmed. Are you sure?')
                    ->form([
                        Forms\Components\Textarea::make('cancel_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (SaleOrder $record, array $data) {
                        try {
                            $service = app(SaleOrderService::class);
                            $service->cancel($record);

                            Notification::make()
                                ->title('Order cancelled successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to cancel order')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(SaleOrder $record) => $record->status === 'Draft' && auth()->user()->can('company.admin')),
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
            'index' => Pages\ListSaleOrders::route('/'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'view' => Pages\ViewSaleOrder::route('/{record}'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'items.product']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', SaleOrder::class);
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'Draft' && auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'Draft' && auth()->user()->can('delete', $record);
    }
}
