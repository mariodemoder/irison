<?php

declare(strict_types=1);

namespace Modules\Bonus\Models;

use App\Models\AppointmentType;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonusType extends Model
{
    use SoftDeletes;

    protected $table = 'bonus_types';

    protected $fillable = [
        'clinic_id',
        'description',
        'sessions',
        'price',
        'expires_at',
    ];

    protected $casts = [
        'sessions' => 'integer',
        'price' => 'decimal:2',
        'expires_at' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentType::class, 'appointment_type_bonus_type', 'bonus_type_id', 'appointment_type_id')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class, 'bonus_type_id');
    }
}
