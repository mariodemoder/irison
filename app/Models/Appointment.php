<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToClinic;
use App\Models\BonusUsage;
use App\Models\CreditUsage;

class Appointment extends Model
{
    use HasFactory;
    use BelongsToClinic;

    protected $fillable = [
        'patient_id',
        'start_time',
        'end_time',
        'status',
        'payment_status',
        'price',
        'invoice_id',
        'notes',
        'payment_type',
        'bonus_id',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time'   => 'datetime:Y-m-d H:i:s',
        'price' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditUsages(): HasMany
    {
        return $this->hasMany(CreditUsage::class);
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function bonusUsage(): HasOne
    {
        return $this->hasOne(BonusUsage::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class, 'bonus_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'invoice_id');
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
