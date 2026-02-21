<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\Concerns\BelongsToClinic;

class Bonus extends Model
{
    use BelongsToClinic;
    protected $fillable = [
        'clinic_id', 'patient_id', 'name', 'total_sessions', 'remaining_sessions', 'price', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    // Expose computed status in model JSON form
    protected $appends = ['status'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(BonusUsage::class);
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
     * Throws exception on insufficient sessions or expired.
     */
    public function consume(int $count = 1, ?Appointment $appointment = null, ?string $notes = null)
    {
        return DB::transaction(function () use ($count, $appointment, $notes) {
            $fresh = static::where('id', $this->id)->lockForUpdate()->first();
            if (!$fresh) throw new \Exception('Bono no encontrado');
            if ($fresh->isExpired()) throw new \Exception('Bono expirado');
            if ($fresh->remaining_sessions < $count) throw new \Exception('No quedan sesiones disponibles en el bono');

            $fresh->remaining_sessions = $fresh->remaining_sessions - $count;
            $fresh->save();

            $usage = $fresh->usages()->create([
                'appointment_id' => $appointment ? $appointment->id : null,
                'used_at' => now(),
                'notes' => $notes,
            ]);

            return $usage;
        });
    }

    /**
     * Revert a usage (increase remaining_sessions and delete or mark usage).
     */
    public function revertUsage(BonusUsage $usage): bool
    {
        return DB::transaction(function () use ($usage) {
            $fresh = static::where('id', $this->id)->lockForUpdate()->first();
            if (!$fresh) throw new \Exception('Bono no encontrado');

            $fresh->remaining_sessions = $fresh->remaining_sessions + 1;
            $fresh->save();

            // delete usage record (could mark reverted instead)
            $usage->delete();

            return true;
        });
    }
}
