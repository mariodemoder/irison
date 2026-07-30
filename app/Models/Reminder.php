<?php

namespace App\Models;

use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class Reminder extends ReminderEloquentModel
{
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
}
