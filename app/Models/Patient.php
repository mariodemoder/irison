<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Bonus;

class Patient extends Model
{
    use BelongsToClinic, SoftDeletes;
      
    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email',
        'birth_date', 'notes', 'nif'
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

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
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
