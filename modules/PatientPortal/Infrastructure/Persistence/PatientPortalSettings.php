<?php

declare(strict_types=1);

namespace Modules\PatientPortal\Infrastructure\Persistence;

use App\Models\Clinic;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración del Portal del Paciente por clínica (estado maestro, horizonte
 * máximo de reserva y política de cancelación). Patrón espejo de BookingPage.
 */
class PatientPortalSettings extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'is_active',
        'max_horizon_days',
        'cancellation_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_horizon_days' => 'integer',
        'cancellation_hours' => 'integer',
    ];

    public const MAX_HORIZON_DAYS_DEFAULT = 60;

    public const CANCELLATION_HOURS_DEFAULT = 24;

    public const IS_ACTIVE_DEFAULT = true;

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Resuelve (y opcionalmente persiste) la configuración del portal para una
     * clínica, aplicando los valores por defecto si aún no existe fila.
     *
     * @return static
     */
    public static function forClinic(int $clinicId, bool $persist = false): static
    {
        $existing = static::query()->where('clinic_id', $clinicId)->first();

        if ($existing) {
            return $existing;
        }

        $defaults = new static([
            'clinic_id' => $clinicId,
            'is_active' => static::IS_ACTIVE_DEFAULT,
            'max_horizon_days' => static::MAX_HORIZON_DAYS_DEFAULT,
            'cancellation_hours' => static::CANCELLATION_HOURS_DEFAULT,
        ]);
        $defaults->exists = false;

        if ($persist) {
            $defaults->save();
        }

        return $defaults;
    }
}