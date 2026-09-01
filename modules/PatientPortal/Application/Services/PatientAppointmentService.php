<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientAuditLog;
use Illuminate\Support\Carbon;
use Modules\PatientPortal\Application\DTOs\AppointmentRequestDTO;
use Modules\PatientPortal\Domain\Exceptions\AppointmentCancellationDeniedException;
use Modules\PatientPortal\Domain\Events\PatientAppointmentCancelled;
use Modules\PatientPortal\Domain\Events\PatientAppointmentRequested;
use Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings;

class PatientAppointmentService
{
    /**
     * Configuración del portal para la clínica del paciente.
     */
    private function settings(Patient $patient): PatientPortalSettings
    {
        return PatientPortalSettings::forClinic($patient->clinic_id);
    }
    public function upcoming(Patient $patient): \Illuminate\Database\Eloquent\Collection
    {
        return Appointment::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('start_time', '>', now())
            ->with(['professional', 'appointmentType'])
            ->orderBy('start_time')
            ->get();
    }

    public function history(Patient $patient, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Appointment::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->with(['professional', 'appointmentType']);

        if (isset($filters['from'])) {
            $query->where('start_time', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('start_time', '<=', $filters['to']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['professional_id'])) {
            $query->where('professional_id', $filters['professional_id']);
        }

        return $query->orderBy('start_time', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function show(Patient $patient, int $appointmentId): Appointment
    {
        return Appointment::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('id', $appointmentId)
            ->with(['professional', 'appointmentType', 'clinic'])
            ->firstOrFail();
    }

    public function request(Patient $patient, AppointmentRequestDTO $dto, ?string $ip = null, ?string $userAgent = null): Appointment
    {
        $startTime = $dto->preferred_date . ' ' . $dto->preferred_time;

        // Horizonte máximo de reserva: la fecha solicitada no puede exceder
        // hoy + max_horizon_days (config del portal).
        $maxHorizon = now()->startOfDay()->addDays($this->settings($patient)->max_horizon_days);
        if (Carbon::parse($startTime)->startOfDay()->gt($maxHorizon)) {
            throw new \DomainException('La fecha excede el horizonte máximo de reserva.');
        }

        $appointment = Appointment::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'start_time' => $startTime,
            'professional_id' => $dto->professional_id,
            'status' => 'scheduled',
            'booking_source' => 'patient_portal',
            'booking_notes' => $dto->notes,
            'notes' => $dto->service_name,
        ]);

        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_appointment_requested',
            'description' => 'Solicitud de cita creada desde el portal',
            'properties' => ['appointment_id' => $appointment->id, 'preferred_date' => $dto->preferred_date],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        event(new PatientAppointmentRequested($patient, $appointment));

        return $appointment;
    }

    public function cancel(Patient $patient, Appointment $appointment, ?string $ip = null, ?string $userAgent = null): void
    {
        // Política de cancelación configurable: antelación mínima en horas
        // (robusto frente a Carbon 3 signed diffInHours).
        $cancellationHours = $this->settings($patient)->cancellation_hours;
        if ($appointment->start_time->lessThan(now()->addHours($cancellationHours))) {
            throw new AppointmentCancellationDeniedException($cancellationHours);
        }

        $appointment->update(['status' => 'canceled']);

        // Restore bonus if applicable
        if ($appointment->bonus_id && method_exists($appointment, 'restoreBonusUsageIfCancelled')) {
            $appointment->restoreBonusUsageIfCancelled();
        }

        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_appointment_cancelled',
            'description' => 'Cita cancelada desde el portal',
            'properties' => ['appointment_id' => $appointment->id],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        event(new PatientAppointmentCancelled($patient, $appointment));
    }

    public function reschedule(Patient $patient, Appointment $appointment, AppointmentRequestDTO $dto, ?string $ip = null, ?string $userAgent = null): Appointment
    {
        // Aplicar la misma política de cancelación a las reprogramaciones: no se
        // puede reprogramar con menos de cancellation_hours de antelación.
        $cancellationHours = $this->settings($patient)->cancellation_hours;
        if ($appointment->start_time->lessThan(now()->addHours($cancellationHours))) {
            throw new AppointmentCancellationDeniedException($cancellationHours);
        }

        $appointment->update(['status' => 'rescheduled']);

        return $this->request($patient, $dto, $ip, $userAgent);
    }
}
