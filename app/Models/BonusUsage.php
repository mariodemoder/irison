<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToClinic;

class BonusUsage extends Model
{
    use BelongsToClinic;
    protected $fillable = [
        'clinic_id', 'bonus_id', 'appointment_id', 'used_at', 'notes', 'appointment_type_id'
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
