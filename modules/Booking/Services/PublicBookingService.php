<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Modules\Booking\Notifications\BookingConfirmation;
use Modules\Booking\Notifications\NewOnlineBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\BookingService;

class PublicBookingService
{
    public function __construct(
        private AvailabilityEngine $availabilityEngine
    ) {}

    public function resolveBookingPage(string $slug): BookingPage
    {
        $page = BookingPage::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            throw new \DomainException('Página de reserva no encontrada o desactivada.');
        }

        return $page;
    }

    public function createAppointment(
        string $slug,
        int $serviceId,
        int $professionalId,
        string $date,
        string $startTime,
        array $patientData
    ): Appointment {
        $page = $this->resolveBookingPage($slug);
        $clinicId = $page->clinic_id;

        $service = BookingService::where('clinic_id', $clinicId)
            ->where('id', $serviceId)
            ->where('is_active', true)
            ->first();

        if (! $service) {
            throw new \DomainException('El servicio seleccionado no está disponible.');
        }

        $duration = $service->duration_minutes;

        $bp = BookingProfessional::where('clinic_id', $clinicId)
            ->where('user_id', $professionalId)
            ->where('allow_online_booking', true)
            ->first();

        if (! $bp) {
            throw new \DomainException('El profesional no está disponible para reserva online.');
        }

        $startDateTime = Carbon::parse($date . ' ' . $startTime);
        $endDateTime = $startDateTime->copy()->addMinutes($duration);

        if ($startDateTime->isPast()) {
            throw new \DomainException('No se puede reservar en una fecha pasada.');
        }

        $maxHorizon = $page->max_horizon_days;
        if ($startDateTime->gt(Carbon::today()->addDays($maxHorizon)->endOfDay())) {
            throw new \DomainException('La fecha excede el horizonte máximo de reserva.');
        }

        $slots = $this->availabilityEngine->getAvailableSlots($clinicId, $serviceId, $professionalId, $date);
        $slotExists = collect($slots)->first(fn ($s) => $s['start'] === $startTime && (int) $s['professional_id'] === $professionalId);

        if (! $slotExists) {
            throw new \DomainException('La franja horaria seleccionada ya no está disponible.');
        }

        return DB::transaction(function () use ($clinicId, $professionalId, $startDateTime, $endDateTime, $patientData, $service) {
            $existing = Appointment::where('clinic_id', $clinicId)
                ->where('professional_id', $professionalId)
                ->where('start_time', '<', $endDateTime)
                ->where('end_time', '>', $startDateTime)
                ->whereNotIn('status', ['canceled', 'cancelled'])
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new \DomainException('La franja horaria se solapa con otra cita existente.');
            }

            $patient = Patient::where('clinic_id', $clinicId)
                ->where('email', $patientData['email'])
                ->first();

            if (! $patient) {
                $patient = Patient::create([
                    'clinic_id' => $clinicId,
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'email' => $patientData['email'],
                    'phone' => $patientData['phone'] ?? null,
                ]);
            }

            $appointment = Appointment::create([
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'professional_id' => $professionalId,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'status' => 'scheduled',
                'payment_status' => 'pending',
                'price' => $service->price ?? 0,
                'payment_type' => 'single',
                'booking_source' => 'online',
                'booking_notes' => $patientData['notes'] ?? null,
                'confirmation_token' => Str::uuid()->toString(),
                'app_type_id' => $service->appointment_type_id,
            ]);

            DB::afterCommit(function () use ($patient, $appointment, $clinicId) {
                try {
                    $patient->notify(new BookingConfirmation($appointment));

                    $clinicUsers = User::where('clinic_id', $clinicId)->where('role', 'owner')->get();
                    Notification::send($clinicUsers, new NewOnlineBooking($appointment));
                } catch (\Throwable $e) {
                    Log::warning('booking.notification_failed', [
                        'event' => 'booking.notification_failed',
                        'appointment_id' => $appointment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            return $appointment;
        });
    }

    public function cancelByToken(string $token): Appointment
    {
        $appointment = Appointment::where('confirmation_token', $token)
            ->where('booking_source', 'online')
            ->first();

        if (! $appointment) {
            throw new \DomainException('Token de cancelación no válido.');
        }

        if ($appointment->start_time->isPast()) {
            throw new \DomainException('No se puede cancelar una cita que ya ha pasado.');
        }

        $appointment->update([
            'status' => 'canceled',
            'confirmation_token' => null,
        ]);

        return $appointment;
    }

    public function findByToken(string $token): Appointment
    {
        $appointment = Appointment::with(['patient', 'professional', 'clinic'])
            ->where('confirmation_token', $token)
            ->first();

        if (! $appointment) {
            throw new \DomainException('Cita no encontrada.');
        }

        return $appointment;
    }
}
