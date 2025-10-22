<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDef extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'entity',
        'key',
        'label',
        'data_type',
        'required',
        'options',
        'default_value',
        'is_indexed',
        'validation_regex',
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_indexed' => 'boolean',
        'options' => 'array',
        'default_value' => 'array',
    ];
}
