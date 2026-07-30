<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'reminders';

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'channel',
        'reminder_type',
        'recipient_email',
        'error_message',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'sent_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
