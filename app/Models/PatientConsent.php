<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientConsent extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'template_id',
        'template_version',
        'appointment_id',
        'status',
        'snapshot',
        'content_html',
        'signature_svg',
        'hash',
        'sent_at',
        'signed_at',
        'revoked_at',
        'ip',
        'user_agent',
        'signed_by',
        'created_by',
        'token',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'template_version' => 'integer',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'token_expires_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'template_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConsentLog::class, 'consent_id');
    }
}
