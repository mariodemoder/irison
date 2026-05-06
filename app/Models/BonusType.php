<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusType extends Model
{
    protected $table = 'bonus_types';

    protected $fillable = [
        'clinic_id',
        'description',
        'sessions',
        'price',
        'expires_at',
    ];

    protected $casts = [
        'sessions' => 'integer',
        'price'    => 'decimal:2',
        'expires_at' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
