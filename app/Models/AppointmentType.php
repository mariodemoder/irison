<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AppointmentType extends Model
{
    protected $table = 'appointment_types';

    protected $fillable = [
        'clinic_id',
        'description',
        'estimated_hours',
        'estimated_minutes',
        'price',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'price' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function bonusTypes(): BelongsToMany
    {
        return $this->belongsToMany(BonusType::class, 'appointment_type_bonus_type', 'appointment_type_id', 'bonus_type_id')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }
}
