<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToClinic;
use App\Services\Counters\CounterService;

class Payment extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id', 'patient_id', 'appointment_id',
        'package_id', 'concept', 'amount', 'method', 'status', 'counter', 'notes', 'paid_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (!empty($payment->counter) || empty($payment->clinic_id)) {
                return;
            }

            $payment->counter = app(CounterService::class)->nextFormatted((int) $payment->clinic_id, 'payments');
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Bonus::class, 'package_id');
    }

    public function creditUsages(): HasMany
    {
        return $this->hasMany(CreditUsage::class);
    }
}
