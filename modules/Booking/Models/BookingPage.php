<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use App\Models\Clinic;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingPage extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'slug',
        'title',
        'is_active',
        'max_horizon_days',
        'cancellation_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_horizon_days' => 'integer',
        'cancellation_hours' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class, 'clinic_id', 'clinic_id');
    }

    public function professionals(): HasMany
    {
        return $this->hasMany(BookingProfessional::class, 'clinic_id', 'clinic_id');
    }
}
