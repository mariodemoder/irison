<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToClinic;
use App\Models\BonusUsage;
use App\Models\CreditUsage;
use App\Models\Reminder;

class Appointment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'start_time',
        'end_time',
        'reminder_24h_sent_at',
        'reminder_2h_sent_at',
        'status',
        'payment_status',
        'price',
        'invoice_id',
        'notes',
        'payment_type',
        'bonus_id',
        'app_type_id',
        'custom_type',
        'booking_source',
        'booking_notes',
        'confirmation_token',
        'professional_id',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time'   => 'datetime:Y-m-d H:i:s',
        'reminder_24h_sent_at' => 'datetime:Y-m-d H:i:s',
        'reminder_2h_sent_at' => 'datetime:Y-m-d H:i:s',
        'price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (Appointment $appointment) {
            if ($appointment->clinic_id) {
                Clinic::withoutGlobalScopes()->where('id', $appointment->clinic_id)->update(['last_activity_at' => now()]);
            }
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

    public function clinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
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

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'app_type_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
    /**
     * Convenience: apply a bonus to this appointment by id using BonusService.
     * Throws exceptions from BonusService on failure.
     */
    public function applyBonus(int $bonusId, ?string $notes = null)
    {
        $service = app(\Modules\Bonus\Services\BonusService::class);
        return $service->useBonusForAppointment($bonusId, $this, $notes);
    }

    /**
     * Convenience: restore bonus usage for this appointment (on cancel).
     */
    public function restoreBonusUsageIfCancelled()
    {
        $service = app(\Modules\Bonus\Services\BonusService::class);
        return $service->restoreBonusIfCancelled($this);
    }
}
