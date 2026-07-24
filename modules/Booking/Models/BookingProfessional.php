<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingProfessional extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'user_id',
        'clinic_id',
        'allow_online_booking',
    ];

    protected $casts = [
        'allow_online_booking' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class, 'professional_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class, 'professional_id');
    }
}
