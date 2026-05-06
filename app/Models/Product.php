<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'reference',
        'name',
        'sale_price',
        'purchase_price',
        'sale_tax',
        'purchase_tax',
        'family',
        'lot',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'sale_tax' => 'decimal:2',
        'purchase_tax' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
