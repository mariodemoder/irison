<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    public const TYPE_APPOINTMENT = 'appointment';
    public const TYPE_BONUS       = 'bonus';
    public const TYPE_PRODUCT     = 'product';
    public const TYPE_MANUAL      = 'manual';

    protected $fillable = [
        'document_id',
        'type',
        'reference_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'buy_price',
        'buy_tax',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
        'buy_price'  => 'decimal:2',
        'buy_tax'    => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Calcula total = quantity * unit_price * (1 + tax_rate / 100)
     */
    public static function computeTotal(float $quantity, float $unitPrice, float $taxRate): float
    {
        return round($quantity * $unitPrice * (1 + $taxRate / 100), 2);
    }
}
