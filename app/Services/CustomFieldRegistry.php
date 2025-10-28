<?php

namespace App\Services;

use App\Models\CustomFieldDef;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CustomFieldRegistry - Dynamic custom field validation and management
 *
 * Provides runtime validation for custom fields defined per company per entity
 */
class CustomFieldRegistry
{
    /**
     * Get custom field definitions for an entity type
     *
     * @param int $companyId
     * @param string $appliesTo (Product, Customer, SaleOrder, SaleOrderItem)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDefinitions(int $companyId, string $appliesTo)
    {
        return CustomFieldDef::where('company_id', $companyId)
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Validate custom field values against their definitions
     *
     * @param int $companyId
     * @param string $appliesTo
     * @param array $attributes Custom field values to validate
     * @return array Validated and cleaned attributes
     * @throws ValidationException
     */
    public function validate(int $companyId, string $appliesTo, array $attributes): array
    {
        $definitions = $this->getDefinitions($companyId, $appliesTo);

        if ($definitions->isEmpty()) {
            return []; // No custom fields defined
        }

        $rules = [];
        $messages = [];

        foreach ($definitions as $def) {
            $fieldKey = $def->field_key;
            $validationRules = $this->buildValidationRules($def);

            if (!empty($validationRules)) {
                $rules[$fieldKey] = $validationRules;
            }

            // Custom error messages
            if ($def->is_required) {
                $messages["{$fieldKey}.required"] = "The {$def->label} field is required.";
            }
        }

        // Perform validation
        $validator = Validator::make($attributes, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Return only validated fields (strip unknown fields)
        $validated = $validator->validated();

        // Apply transformations (e.g., ensure proper types)
        return $this->transformValues($definitions, $validated);
    }

    /**
     * Build Laravel validation rules from field definition
     *
     * @param CustomFieldDef $def
     * @return array|string
     */
    protected function buildValidationRules(CustomFieldDef $def)
    {
        $rules = [];

        // Required check
        if ($def->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        switch ($def->field_type) {
            case 'text':
            case 'textarea':
                $rules[] = 'string';
                if (!empty($def->validation_rules['max_length'])) {
                    $rules[] = 'max:' . $def->validation_rules['max_length'];
                }
                if (!empty($def->validation_rules['min_length'])) {
                    $rules[] = 'min:' . $def->validation_rules['min_length'];
                }
                break;

            case 'number':
            case 'decimal':
                $rules[] = 'numeric';
                if (isset($def->validation_rules['min'])) {
                    $rules[] = 'min:' . $def->validation_rules['min'];
                }
                if (isset($def->validation_rules['max'])) {
                    $rules[] = 'max:' . $def->validation_rules['max'];
                }
                break;

            case 'integer':
                $rules[] = 'integer';
                if (isset($def->validation_rules['min'])) {
                    $rules[] = 'min:' . $def->validation_rules['min'];
                }
                if (isset($def->validation_rules['max'])) {
                    $rules[] = 'max:' . $def->validation_rules['max'];
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'date':
                $rules[] = 'date';
                if (!empty($def->validation_rules['after'])) {
                    $rules[] = 'after:' . $def->validation_rules['after'];
                }
                if (!empty($def->validation_rules['before'])) {
                    $rules[] = 'before:' . $def->validation_rules['before'];
                }
                break;

            case 'datetime':
                $rules[] = 'date';
                break;

            case 'select':
                if (!empty($def->options)) {
                    $allowedValues = collect($def->options)->pluck('value')->toArray();
                    $rules[] = 'in:' . implode(',', $allowedValues);
                }
                break;

            case 'multiselect':
                $rules[] = 'array';
                if (!empty($def->options)) {
                    $allowedValues = collect($def->options)->pluck('value')->toArray();
                    $rules[] = 'in:' . implode(',', $allowedValues);
                }
                break;

            case 'email':
                $rules[] = 'email';
                break;

            case 'url':
                $rules[] = 'url';
                break;

            case 'phone':
                $rules[] = 'string';
                $rules[] = 'regex:/^[\d\s\-\+\(\)]+$/';
                break;
        }

        // Additional custom validation rules from definition
        if (!empty($def->validation_rules['custom_rule'])) {
            $rules[] = $def->validation_rules['custom_rule'];
        }

        return implode('|', $rules);
    }

    /**
     * Transform validated values to proper types
     *
     * @param \Illuminate\Database\Eloquent\Collection $definitions
     * @param array $values
     * @return array
     */
    protected function transformValues($definitions, array $values): array
    {
        $transformed = [];

        foreach ($definitions as $def) {
            $fieldKey = $def->field_key;

            if (!array_key_exists($fieldKey, $values)) {
                continue;
            }

            $value = $values[$fieldKey];

            // Type casting
            switch ($def->field_type) {
                case 'number':
                case 'decimal':
                    $transformed[$fieldKey] = $value !== null ? (float) $value : null;
                    break;

                case 'integer':
                    $transformed[$fieldKey] = $value !== null ? (int) $value : null;
                    break;

                case 'boolean':
                    $transformed[$fieldKey] = (bool) $value;
                    break;

                case 'multiselect':
                    $transformed[$fieldKey] = is_array($value) ? $value : [];
                    break;

                default:
                    $transformed[$fieldKey] = $value;
            }
        }

        return $transformed;
    }

    /**
     * Create a new custom field definition
     *
     * @param int $companyId
     * @param array $data
     * @return CustomFieldDef
     */
    public function createDefinition(int $companyId, array $data): CustomFieldDef
    {
        $validator = Validator::make($data, [
            'applies_to' => 'required|string|in:Product,Customer,SaleOrder,SaleOrderItem',
            'field_key' => 'required|string|regex:/^[a-z_][a-z0-9_]*$/|max:50',
            'label' => 'required|string|max:100',
            'field_type' => 'required|string|in:text,textarea,number,integer,decimal,boolean,date,datetime,select,multiselect,email,url,phone',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer',
            'options' => 'nullable|array',
            'validation_rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check uniqueness of field_key per company and entity
        $exists = CustomFieldDef::where('company_id', $companyId)
            ->where('applies_to', $data['applies_to'])
            ->where('field_key', $data['field_key'])
            ->exists();

        if ($exists) {
            throw new \Exception("Field key '{$data['field_key']}' already exists for {$data['applies_to']}");
        }

        return CustomFieldDef::create([
            'company_id' => $companyId,
            'applies_to' => $data['applies_to'],
            'field_key' => $data['field_key'],
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'is_required' => $data['is_required'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? 0,
            'options' => $data['options'] ?? null,
            'validation_rules' => $data['validation_rules'] ?? null,
        ]);
    }

    /**
     * Update a custom field definition
     *
     * @param CustomFieldDef $definition
     * @param array $data
     * @return CustomFieldDef
     */
    public function updateDefinition(CustomFieldDef $definition, array $data): CustomFieldDef
    {
        $validator = Validator::make($data, [
            'label' => 'sometimes|string|max:100',
            'is_required' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer',
            'options' => 'sometimes|array',
            'validation_rules' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $definition->update($validator->validated());

        return $definition->fresh();
    }

    /**
     * Delete a custom field definition
     *
     * @param CustomFieldDef $definition
     * @return bool
     */
    public function deleteDefinition(CustomFieldDef $definition): bool
    {
        return $definition->delete();
    }

    /**
     * Get schema for frontend forms
     *
     * @param int $companyId
     * @param string $appliesTo
     * @return array
     */
    public function getFormSchema(int $companyId, string $appliesTo): array
    {
        $definitions = $this->getDefinitions($companyId, $appliesTo);

        return $definitions->map(function ($def) {
            return [
                'key' => $def->field_key,
                'label' => $def->label,
                'type' => $def->field_type,
                'required' => $def->is_required,
                'options' => $def->options,
                'validation' => $def->validation_rules,
                'order' => $def->display_order,
            ];
        })->toArray();
    }
}
