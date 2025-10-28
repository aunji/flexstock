<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomFieldDefResource\Pages;
use App\Models\CustomFieldDef;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefResource extends Resource
{
    protected static ?string $model = CustomFieldDef::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Custom Fields';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Field Definition')
                    ->schema([
                        Forms\Components\Select::make('applies_to')
                            ->label('Entity Type')
                            ->required()
                            ->options([
                                'Product' => 'Product',
                                'Customer' => 'Customer',
                                'SaleOrder' => 'Sale Order',
                            ])
                            ->searchable()
                            ->placeholder('Select entity type'),

                        Forms\Components\TextInput::make('field_key')
                            ->label('Field Key')
                            ->required()
                            ->maxLength(100)
                            ->unique(CustomFieldDef::class, 'field_key', ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', app('current_company_id'));
                            })
                            ->helperText('Unique identifier for this field (e.g., warranty_months)')
                            ->placeholder('e.g., warranty_months'),

                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Display label for this field'),

                        Forms\Components\Select::make('field_type')
                            ->label('Data Type')
                            ->required()
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Text Area',
                                'number' => 'Number',
                                'integer' => 'Integer',
                                'decimal' => 'Decimal',
                                'boolean' => 'Boolean (Yes/No)',
                                'date' => 'Date',
                                'datetime' => 'Date Time',
                                'select' => 'Select (Single)',
                                'multiselect' => 'Select (Multiple)',
                                'email' => 'Email',
                                'url' => 'URL',
                                'phone' => 'Phone',
                            ])
                            ->searchable()
                            ->reactive()
                            ->placeholder('Select data type'),

                        Forms\Components\Toggle::make('is_required')
                            ->label('Required Field')
                            ->default(false)
                            ->inline(false),

                        Forms\Components\Toggle::make('is_indexed')
                            ->label('Create JSON Index')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Improves query performance for this field'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\TextInput::make('display_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Order in which field appears in forms'),
                    ])->columns(2),

                Forms\Components\Section::make('Options & Validation')
                    ->schema([
                        Forms\Components\KeyValue::make('options')
                            ->label('Select Options')
                            ->keyLabel('Value')
                            ->valueLabel('Label')
                            ->visible(fn($get) => in_array($get('field_type'), ['select', 'multiselect']))
                            ->helperText('Define options for select fields'),

                        Forms\Components\Textarea::make('placeholder')
                            ->maxLength(255)
                            ->rows(2)
                            ->placeholder('Optional placeholder text'),

                        Forms\Components\KeyValue::make('validation_rules')
                            ->label('Validation Rules')
                            ->keyLabel('Rule')
                            ->valueLabel('Value')
                            ->helperText('e.g., min: 0, max: 100, regex: ^[A-Z]+$'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('applies_to')
                    ->label('Entity')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('field_key')
                    ->label('Field Key')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('field_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_indexed')
                    ->label('Indexed')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('applies_to')
                    ->label('Entity Type')
                    ->options([
                        'Product' => 'Product',
                        'Customer' => 'Customer',
                        'SaleOrder' => 'Sale Order',
                    ]),

                Tables\Filters\SelectFilter::make('field_type')
                    ->label('Data Type')
                    ->options([
                        'text' => 'Text',
                        'number' => 'Number',
                        'boolean' => 'Boolean',
                        'date' => 'Date',
                        'select' => 'Select',
                    ]),

                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomFieldDefs::route('/'),
            'create' => Pages\CreateCustomFieldDef::route('/create'),
            'edit' => Pages\EditCustomFieldDef::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', CustomFieldDef::class);
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
