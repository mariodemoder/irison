<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\DTOs;

use Modules\Notifications\Domain\Enums\EmailCategory;
use Modules\Notifications\Infrastructure\Persistence\EmailLogEloquentModel;

class EmailLogResponseData
{
    public static function fromModel(EmailLogEloquentModel $log, ?array $history = null): array
    {
        $patient = $log->patient;
        $appointment = $log->appointment;
        $clinic = $log->clinic;

        $payload = [
            'id' => $log->id,
            'category' => $log->category,
            'category_label' => EmailCategory::labelFor((string) $log->category),
            'to_email' => $log->to_email,
            'from_email' => $log->from_email,
            'subject' => $log->subject,
            'status' => $log->status,
            'error_message' => $log->error_message,
            'sent_at' => $log->sent_at,
            'created_at' => $log->created_at,
            'patient_id' => $log->patient_id,
            'appointment_id' => $log->appointment_id,
            'reminder_id' => $log->reminder_id,
            'clinic' => $clinic ? [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
                'phone' => $clinic->phone,
                'timezone' => $clinic->timezone,
            ] : null,
            'patient' => $patient ? [
                'id' => $patient->id,
                'counter' => $patient->counter,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ] : null,
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'start_time' => $appointment->start_time,
                'status' => $appointment->status,
            ] : null,
        ];

        if ($history !== null) {
            $payload['history'] = $history;
        }

        return $payload;
    }
}
