<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\Concerns\BelongsToClinic;
use App\Services\Counters\CounterService;

class Bonus extends Model
{
    use BelongsToClinic;
    protected $fillable = [
        'clinic_id', 'patient_id', 'bonus_type_id', 'name', 'total_sessions', 'remaining_sessions', 'price', 'invoice_id', 'counter', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    // Expose computed status in model JSON form
    protected $appends = ['status'];

    protected static function booted(): void
    {
        static::creating(function (Bonus $bonus) {
            if (!empty($bonus->counter) || empty($bonus->clinic_id)) {
                return;
            }

            $bonus->counter = app(CounterService::class)->nextFormatted((int) $bonus->clinic_id, 'bonuses');
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

    public function bonusType(): BelongsTo
    {
        return $this->belongsTo(BonusType::class, 'bonus_type_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'invoice_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(BonusUsage::class);
    }

    public function sessionLines(): HasMany
    {
        return $this->hasMany(\Modules\Bonus\Models\BonusSessionLine::class);
    }

    /**
     * Check if this bonus has per-type session lines (new multi-type system).
     */
    public function hasSessionLines(): bool
    {
        return $this->sessionLines()->exists();
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) return false;
        return now()->greaterThan($this->expires_at->copy()->endOfDay());
    }

    /**
     * Compute business status for the bonus.
     * Values: active | last | exhausted | expired
     */
    public function getStatusAttribute(): string
    {
        if ($this->isExpired()) return 'expired';

        if ($this->remaining_sessions <= 0) return 'exhausted';

        if ($this->remaining_sessions === 1) return 'last';

        return 'active';
    }

    /**
     * Consume sessions from this bonus. Returns the created BonusUsage.
     * If the bonus has session lines, decrements the matching type.
     * Throws exception on insufficient sessions or expired.
     */
    public function consume(int $count = 1, ?Appointment $appointment = null, ?string $notes = null, ?int $appointmentTypeId = null)
    {
        return DB::transaction(function () use ($count, $appointment, $notes, $appointmentTypeId) {
            $fresh = static::where('id', $this->id)->lockForUpdate()->first();
            if (!$fresh) throw new \Exception('Bono no encontrado');
            if ($fresh->isExpired()) throw new \Exception('Bono expirado');
            if ($fresh->remaining_sessions < $count) throw new \Exception('No quedan sesiones disponibles en el bono');

            // Multi-type: decrement the matching session line
            if ($fresh->hasSessionLines() && $appointmentTypeId) {
                $line = $fresh->sessionLines()
                    ->where('appointment_type_id', $appointmentTypeId)
                    ->lockForUpdate()
                    ->first();

                if (!$line) {
                    throw new \Exception('Tipo de cita no incluido en este bono');
                }
                if ($line->remaining_quantity < $count) {
                    throw new \Exception('No quedan sesiones de este tipo en el bono');
                }

                $line->remaining_quantity = $line->remaining_quantity - $count;
                $line->save();
            }

            $fresh->remaining_sessions = $fresh->remaining_sessions - $count;
            $fresh->save();

            $usage = $fresh->usages()->create([
                'appointment_id' => $appointment ? $appointment->id : null,
                'used_at' => now(),
                'notes' => $notes,
                'appointment_type_id' => $appointmentTypeId,
            ]);

            return $usage;
        });
    }

    /**
     * Revert a usage (increase remaining_sessions and delete or mark usage).
     * If the bonus has session lines, restores the matching type.
     */
    public function revertUsage(BonusUsage $usage): bool
    {
        return DB::transaction(function () use ($usage) {
            $fresh = static::where('id', $this->id)->lockForUpdate()->first();
            if (!$fresh) throw new \Exception('Bono no encontrado');

            // Multi-type: restore the matching session line
            if ($fresh->hasSessionLines() && $usage->appointment_type_id) {
                $line = $fresh->sessionLines()
                    ->where('appointment_type_id', $usage->appointment_type_id)
                    ->lockForUpdate()
                    ->first();

                if ($line) {
                    $line->remaining_quantity = $line->remaining_quantity + 1;
                    $line->save();
                }
            }

            $fresh->remaining_sessions = $fresh->remaining_sessions + 1;
            $fresh->save();

            $usage->delete();

            return true;
        });
    }
}
