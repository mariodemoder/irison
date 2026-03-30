<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Bonus;
use App\Models\CreditUsage;
use App\Services\Counters\CounterService;

class Patient extends Model
{
    use BelongsToClinic, SoftDeletes;
      
    protected $fillable = [
        'clinic_id', 'first_name', 'last_name', 'phone', 'email',
        'birth_date', 'notes', 'nif', 'address', 'zip', 'province', 'country', 'counter'
    ];

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            if (empty($patient->counter) && !empty($patient->clinic_id)) {
                $patient->counter = app(CounterService::class)->nextFormatted((int) $patient->clinic_id, 'patients');
            }
        });
    }

    /**
     * Añadir accessors calculados a la serialización.
     */
    protected $appends = ['name'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Bonus::class, 'patient_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function clinicalRecords(): HasMany
    {
        return $this->hasMany(ClinicalRecord::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    public function creditUsages(): HasMany
    {
        return $this->hasMany(CreditUsage::class);
    }

    public function creditUsed(): float
    {
        return (float) $this->creditUsages()
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    public function availableCredit(): float
    {
        $creditTotal = (float) $this->payments()
            ->where('concept', 'credit')
            ->where('status', '!=', 'refunded')
            ->sum('amount');

        return max($creditTotal - $this->creditUsed(), 0.0);
    }

    /**
     * Accessor para obtener el nombre completo del paciente.
     * Ejemplo: `$patient->name` devolverá "First Last".
     */
    public function getNameAttribute(): string
    {
        $first = $this->first_name ?? '';
        $last = $this->last_name ?? '';

        return trim(sprintf('%s %s', $first, $last));
    }
}
