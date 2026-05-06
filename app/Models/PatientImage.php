<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToClinic;

class PatientImage extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'description',
        'path',
        'mime_type',
        'size_bytes',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
