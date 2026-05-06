<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentType extends Model
{
    protected $table = 'appointment_types';

    protected $fillable = [
        'clinic_id',
        'description',
        'estimated_hours',
        'estimated_minutes',
        'price',
        'payment_type',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'price' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
