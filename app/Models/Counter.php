<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Counter extends Model
{
    protected $table = 'counters_clinics';

    protected $fillable = [
        'clinic_id',
        'prefix',
        'last_number',
        'table_type',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
