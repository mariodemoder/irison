<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToClinic;

class PatientAuditLog extends Model
{
    use BelongsToClinic;

    const UPDATED_AT = null;

    protected $fillable = [
        'clinic_id', 'patient_id', 'event', 'description', 'properties', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
