<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Services\Counters\CounterService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToClinic;

    public const UPDATED_AT = null;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'type',
        'type_from',
        'counter',
        'clinic_name',
        'clinic_nif',
        'clinic_address',
        'clinic_zip',
        'clinic_province',
        'clinic_country',
        'user_full_name',
        'from_id',
        'typeinvoice',
        'patient_nif',
        'patient_full_name',
        'patient_email',
        'patient_phone',
        'patient_address',
        'patient_zip',
        'date',
        'amount',
        'notes',
        'status',
        'is_payed',
        'is_sended',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'is_payed' => 'boolean',
        'is_sended' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            if (!empty($document->counter) || empty($document->clinic_id)) {
                return;
            }

            $document->counter = app(CounterService::class)->nextFormatted((int) $document->clinic_id, 'documents');
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
