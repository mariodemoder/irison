<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToClinic;

class Reminder extends Model
{
    use BelongsToClinic;
    
    protected $fillable = [
        'clinic_id', 'appointment_id', 'channel',
        'sent_at', 'status'
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
