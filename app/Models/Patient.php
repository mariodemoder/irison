<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Bonus;
use App\Models\CreditUsage;
use App\Services\Counters\CounterService;
use App\Notifications\PatientResetPasswordNotification;
use Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings;

class Patient extends Model implements CanResetPasswordContract
{
    use BelongsToClinic, SoftDeletes, Notifiable, HasApiTokens, CanResetPassword;

    protected $fillable = [
        'clinic_id', 'first_name', 'last_name', 'phone', 'email',
        'birth_date', 'notes', 'nif', 'address', 'zip', 'city', 'province', 'country', 'counter',
        // Portal fields
        'password', 'email_verified_at', 'last_login_at', 'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

    public function patientImages(): HasMany
    {
        return $this->hasMany(PatientImage::class);
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

    /**
     * El paciente puede usar el Portal del Paciente.
     *
     * Requiere que la clínica tenga configurado un slug de portal (las clínicas
     * sin slug quedan fuera de todo el circuito del portal), que el portal esté
     * activado a nivel de clínica (interruptor maestro, patient_portal_settings.is_active)
     * y que el acceso del paciente esté activado (opt-in, status = 'active').
     */
    public function canUsePortal(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (empty($this->clinic?->slug)) {
            return false;
        }

        return PatientPortalSettings::forClinic($this->clinic_id)->is_active;
    }

    /**
     * Clave de almacenamiento del token de restablecimiento de contraseña.
     *
     * Se usa el id del paciente (globalmente único) en lugar del email, de modo
     * que cada clinic-patient tenga su propio token independiente y un email
     * compartido entre clínicas no invalide el token de la otra.
     */
    public function getEmailForPasswordReset(): string
    {
        return (string) $this->id;
    }

    /**
     * Notificación de restablecimiento de contraseña del Portal del Paciente.
     * Apunta a /patient/reset-password (SPA del paciente) con branding de clínica.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PatientResetPasswordNotification($token));
    }
}
