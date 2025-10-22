<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'sku',
        'name',
        'base_uom',
        'price',
        'cost',
        'stock_qty',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock_qty' => 'decimal:3',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleOrderItems(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class);
    }
}
