<?php

namespace App\Support;

use App\Models\CustomFieldDef;
use App\Services\CustomFieldRegistry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * CustomFieldFormBuilder
 *
 * Generates Filament form components from CustomFieldDef definitions
 */
class CustomFieldFormBuilder
{
    protected CustomFieldRegistry $registry;

    public function __construct()
    {
        $this->registry = app(CustomFieldRegistry::class);
    }

    /**
     * Build form components for a given entity type
     *
     * @param string $entityType (Product, Customer, SaleOrder)
     * @return array Array of Filament form components
     */
    public function buildComponents(string $entityType): array
    {
        $companyId = app('current_company_id');

        if (!$companyId) {
            return [];
        }

        // Get custom field definitions for this entity
        $fieldDefs = CustomFieldDef::where('company_id', $companyId)
            ->where('applies_to', $entityType)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $components = [];

        foreach ($fieldDefs as $fieldDef) {
            $component = $this->mapFieldToComponent($fieldDef);

            if ($component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Map a CustomFieldDef to a Filament form component
     */
    protected function mapFieldToComponent(CustomFieldDef $fieldDef)
    {
        $fieldKey = "attributes.{$fieldDef->field_key}";

        return match ($fieldDef->field_type) {
            'text', 'email', 'url', 'phone' => TextInput::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->maxLength(255)
                ->email($fieldDef->field_type === 'email')
                ->url($fieldDef->field_type === 'url')
                ->tel($fieldDef->field_type === 'phone')
                ->placeholder($fieldDef->placeholder),

            'textarea' => Textarea::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->rows(3)
                ->placeholder($fieldDef->placeholder),

            'number', 'integer', 'decimal' => TextInput::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->numeric()
                ->minValue($fieldDef->validation_rules['min'] ?? null)
                ->maxValue($fieldDef->validation_rules['max'] ?? null)
                ->step($fieldDef->field_type === 'decimal' ? 0.01 : 1)
                ->placeholder($fieldDef->placeholder),

            'boolean' => Toggle::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->default(false),

            'date' => DatePicker::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->displayFormat('Y-m-d')
                ->placeholder($fieldDef->placeholder),

            'datetime' => DateTimePicker::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->displayFormat('Y-m-d H:i:s')
                ->placeholder($fieldDef->placeholder),

            'select' => Select::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->options($this->getSelectOptions($fieldDef))
                ->searchable()
                ->placeholder($fieldDef->placeholder ?? 'Select...'),

            'multiselect' => Select::make($fieldKey)
                ->label($fieldDef->label)
                ->required($fieldDef->is_required)
                ->options($this->getSelectOptions($fieldDef))
                ->multiple()
                ->searchable()
                ->placeholder($fieldDef->placeholder ?? 'Select multiple...'),

            default => null,
        };
    }

    /**
     * Get options for select/multiselect fields
     */
    protected function getSelectOptions(CustomFieldDef $fieldDef): array
    {
        $options = $fieldDef->options ?? [];

        // If options is already an associative array, return as-is
        if (is_array($options) && count($options) > 0) {
            // Check if it's an indexed array or associative
            $firstKey = array_key_first($options);
            if (is_int($firstKey)) {
                // Convert indexed array to associative (value => label)
                return array_combine($options, $options);
            }
            return $options;
        }

        return [];
    }

    /**
     * Validate custom field values
     *
     * @param string $entityType
     * @param array $attributes
     * @return array Validation errors
     */
    public function validate(string $entityType, array $attributes): array
    {
        $companyId = app('current_company_id');

        if (!$companyId) {
            return [];
        }

        try {
            $this->registry->validate($companyId, $entityType, $attributes);
            return [];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e->errors();
        }
    }
}
