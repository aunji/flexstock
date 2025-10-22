<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'product_id',
        'ref_type',
        'ref_id',
        'qty_in',
        'qty_out',
        'balance_after',
        'notes',
    ];

    protected $casts = [
        'qty_in' => 'decimal:3',
        'qty_out' => 'decimal:3',
        'balance_after' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
