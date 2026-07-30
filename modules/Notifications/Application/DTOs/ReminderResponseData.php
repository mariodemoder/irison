<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\DTOs;

use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ReminderResponseData
{
    public static function fromReminderWithAppointment(
        ReminderEloquentModel $reminder,
        ?array $history = null,
        ?array $clinic = null,
    ): array {
        $patient = $reminder->appointment?->patient;

        $payload = [
            'id' => $reminder->id,
            'appointment_id' => $reminder->appointment_id,
            'channel' => $reminder->channel,
            'reminder_type' => $reminder->reminder_type,
            'recipient_email' => $reminder->recipient_email,
            'status' => $reminder->status,
            'error_message' => $reminder->error_message,
            'sent_at' => $reminder->sent_at,
            'created_at' => $reminder->created_at,
            'appointment' => $reminder->appointment ? [
                'id' => $reminder->appointment->id,
                'start_time' => $reminder->appointment->start_time,
                'status' => $reminder->appointment->status,
                'reminder_24h_sent_at' => $reminder->appointment->reminder_24h_sent_at,
                'reminder_2h_sent_at' => $reminder->appointment->reminder_2h_sent_at,
            ] : null,
            'patient' => $patient ? [
                'id' => $patient->id,
                'counter' => $patient->counter,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ] : null,
        ];

        if ($clinic !== null) {
            $payload['clinic'] = $clinic;
        }
        if ($history !== null) {
            $payload['history'] = $history;
        }

        return $payload;
    }
}
