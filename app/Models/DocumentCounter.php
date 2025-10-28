<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'doc_type',
        'period',
        'last_num',
    ];

    protected $casts = [
        'last_num' => 'integer',
    ];
}
