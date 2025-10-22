<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'discount_type',
        'discount_value',
        'notes',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'tier_code', 'code');
    }
}
