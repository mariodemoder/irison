<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Patient;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLogEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'email_logs';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'appointment_id',
        'reminder_id',
        'category',
        'to_email',
        'from_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
