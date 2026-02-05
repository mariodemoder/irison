<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToClinic;

class Patient extends Model
{
    use BelongsToClinic;
      
    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email',
        'birth_date', 'notes'
    ];

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
        return $this->hasMany(Pack::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function clinicalRecords(): HasMany
    {
        return $this->hasMany(ClinicalRecord::class);
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
