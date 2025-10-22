<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'phone',
        'name',
        'tier_code',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'tier_code', 'code');
    }

    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }
}
