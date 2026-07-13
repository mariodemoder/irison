<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Reminder;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReminderService
{
    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $query = Reminder::query()
            ->with([
                'appointment:id,clinic_id,patient_id,start_time,status',
                'appointment.patient:id,counter,first_name,last_name,email',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reminder_type'])) {
            $query->where('reminder_type', $filters['reminder_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate(DB::raw('COALESCE(sent_at, created_at)'), '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate(DB::raw('COALESCE(sent_at, created_at)'), '<=', $filters['to_date']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('recipient_email', 'like', "%{$term}%")
                    ->orWhereHas('appointment.patient', function (Builder $patientQuery) use ($term): void {
                        $patientQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('counter', 'like', "%{$term}%");
                    });
            });
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn (Reminder $reminder) => $this->transformReminder($reminder))->values()->all(),
            'meta' => $this->paginationMeta($paginator),
            'summary' => [
                'count' => (clone $query)->count(),
                'sent_count' => (clone $query)->where('status', 'sent')->count(),
                'failed_count' => (clone $query)->where('status', 'failed')->count(),
            ],
        ];
    }

    public function show(Reminder $reminder): array
    {
        $reminder->load([
            'appointment:id,clinic_id,patient_id,start_time,status,reminder_24h_sent_at,reminder_2h_sent_at',
            'appointment.patient:id,counter,first_name,last_name,email,phone',
            'appointment.clinic:id,name,timezone,email,phone',
        ]);

        $history = Reminder::query()
            ->where('appointment_id', $reminder->appointment_id)
            ->where('reminder_type', $reminder->reminder_type)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Reminder $attempt) => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'recipient_email' => $attempt->recipient_email,
                'error_message' => $attempt->error_message,
                'sent_at' => $attempt->sent_at,
                'created_at' => $attempt->created_at,
            ])
            ->values()
            ->all();

        $payload = $this->transformReminder($reminder);
        $payload['clinic'] = $reminder->appointment?->clinic ? [
            'id' => $reminder->appointment->clinic->id,
            'name' => $reminder->appointment->clinic->name,
            'timezone' => $reminder->appointment->clinic->timezone,
            'email' => $reminder->appointment->clinic->email,
            'phone' => $reminder->appointment->clinic->phone,
        ] : null;
        $payload['history'] = $history;

        return $payload;
    }

    public function resend(Reminder $reminder): Reminder
    {
        $appointment = $reminder->appointment()
            ->with(['patient', 'clinic'])
            ->firstOrFail();

        return $this->sendAppointmentReminder($appointment, (string) $reminder->reminder_type, true, true);
    }

    public function sendAppointmentReminder(
        Appointment $appointment,
        string $reminderType,
        bool $markAppointmentSent = true,
        bool $throwOnFailure = false,
    ): Reminder {
        $appointment->loadMissing(['patient', 'clinic']);

        $email = trim((string) $appointment->patient?->email);

        if ($email === '') {
            $message = 'Paciente sin email para enviar recordatorio.';
            $failedReminder = $this->createReminderRecord($appointment, $reminderType, $email, 'failed', null, $message);

            Log::warning('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType,
                'reason' => 'missing_email',
            ]);

            if ($throwOnFailure) {
                throw new DomainException($message);
            }

            return $failedReminder;
        }

        try {
            Mail::to($email)->queue(new AppointmentReminderMail($appointment, $this->hoursBeforeForType($reminderType)));

            if ($markAppointmentSent) {
                $this->markAppointmentReminderAsSent($appointment, $reminderType);
            }

            $sentReminder = $this->createReminderRecord($appointment, $reminderType, $email, 'queued', now(), null);

            Log::info('reminder.queued', [
                'event' => 'reminder.queued',
                'result' => 'queued',
                'reminder_id' => $sentReminder->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType,
                'recipient_domain' => $this->extractEmailDomain($email),
            ]);

            return $sentReminder;
        } catch (Throwable $e) {
            $failedReminder = $this->createReminderRecord($appointment, $reminderType, $email, 'failed', null, $e->getMessage());

            Log::error('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'reminder_id' => $failedReminder->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType,
                'recipient_domain' => $this->extractEmailDomain($email),
                'error_code' => class_basename($e),
            ]);

            if ($throwOnFailure) {
                throw new DomainException($e->getMessage());
            }

            return $failedReminder;
        }
    }

    private function createReminderRecord(
        Appointment $appointment,
        string $reminderType,
        string $recipientEmail,
        string $status,
        $sentAt,
        ?string $errorMessage,
    ): Reminder {
        return Reminder::create([
            'clinic_id' => (int) $appointment->clinic_id,
            'appointment_id' => (int) $appointment->id,
            'channel' => 'email',
            'reminder_type' => $reminderType,
            'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
            'error_message' => $errorMessage,
            'sent_at' => $sentAt,
            'status' => $status,
        ]);
    }

    private function markAppointmentReminderAsSent(Appointment $appointment, string $reminderType): void
    {
        $column = $this->sentAtColumnForType($reminderType);

        if ($column === null || !empty($appointment->{$column})) {
            return;
        }

        $appointment->forceFill([
            $column => now(),
        ])->save();
    }

    private function sentAtColumnForType(string $reminderType): ?string
    {
        return match ($reminderType) {
            '24h' => 'reminder_24h_sent_at',
            '2h' => 'reminder_2h_sent_at',
            default => null,
        };
    }

    private function hoursBeforeForType(string $reminderType): int
    {
        return match ($reminderType) {
            '24h' => 24,
            '2h' => 2,
            default => throw new DomainException('Tipo de recordatorio no soportado.'),
        };
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function transformReminder(Reminder $reminder): array
    {
        $patient = $reminder->appointment?->patient;

        return [
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
    }

    private function extractEmailDomain(string $email): ?string
    {
        $normalized = trim(strtolower($email));
        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return null;
        }

        return substr($normalized, strpos($normalized, '@') + 1) ?: null;
    }
}
