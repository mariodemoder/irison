<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToClinic;
use App\Models\BonusUsage;

class Appointment extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'patient_id',
        'start_time',
        'end_time',
        'status',
        'payment_status',
        'notes',
        'payment_type',
        'bonus_id',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time'   => 'datetime:Y-m-d H:i:s',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function bonusUsage(): HasOne
    {
        return $this->hasOne(BonusUsage::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class, 'bonus_id');
    }

    /**
     * Convenience: apply a bonus to this appointment by id using BonusService.
     * Throws exceptions from BonusService on failure.
     */
    public function applyBonus(int $bonusId, ?string $notes = null)
    {
        $service = new \App\Services\Bonus\BonusService();
        return $service->useBonusForAppointment($bonusId, $this, $notes);
    }

    /**
     * Convenience: restore bonus usage for this appointment (on cancel).
     */
    public function restoreBonusUsageIfCancelled()
    {
        $service = new \App\Services\Bonus\BonusService();
        return $service->restoreBonusIfCancelled($this);
    }
}
