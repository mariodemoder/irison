<?php

declare(strict_types=1);

namespace Modules\Bonus\Models;

use App\Models\AppointmentType;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusSessionLine extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'bonus_id',
        'appointment_type_id',
        'quantity',
        'remaining_quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'remaining_quantity' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Clinic::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function isExhausted(): bool
    {
        return $this->remaining_quantity <= 0;
    }
}
