<?php

declare(strict_types=1);

namespace Modules\PatientPortal\Infrastructure\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentReminderSent;
use App\Events\AppointmentUpdated;
use App\Events\ConsentSent;
use App\Events\PaymentCreated;
use App\Models\Appointment;
use App\Models\PatientPortalNotification;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Creates Patient Portal in-app notifications from domain events.
 *
 * Every notification enforces tenant + patient identity isolation by copying
 * clinic_id and patient_id from the source resource. Failures never bubble up
 * to the request that triggered the event.
 */
class CreatePatientPortalNotifications
{
    /**
     * Clinic confirmed an appointment.
     */
    public function handleAppointmentUpdated(AppointmentUpdated $event): void
    {
        try {
            $changed = $event->changedAttributes ?? [];

            // Only notify when the status is transitioning TO confirmed.
            if (!array_key_exists('status', $changed) || $changed['status'] !== 'confirmed') {
                return;
            }

            $appointment = $event->appointment;

            if (!$this->isNotifiable($appointment)) {
                return;
            }

            $date = $this->formatDate($appointment->start_time);

            $this->create($appointment, 'appointment_confirmed', [
                'title' => 'Cita confirmada',
                'body' => "Su cita del {$date} ha sido confirmada.",
                'data' => ['appointment_id' => $appointment->id],
            ]);
        } catch (Throwable $e) {
            $this->logFailure('appointment_confirmed', $event->appointment->id ?? null, $e);
        }
    }

    /**
     * Clinic cancelled an appointment (patient self-cancellation does not dispatch this event).
     */
    public function handleAppointmentCancelled(AppointmentCancelled $event): void
    {
        try {
            $appointment = $event->appointment;

            if (!$this->isNotifiable($appointment)) {
                return;
            }

            $date = $this->formatDate($appointment->start_time);

            $this->create($appointment, 'appointment_cancelled', [
                'title' => 'Cita cancelada',
                'body' => "Su cita del {$date} ha sido cancelada.",
                'data' => ['appointment_id' => $appointment->id],
            ]);
        } catch (Throwable $e) {
            $this->logFailure('appointment_cancelled', $event->appointment->id ?? null, $e);
        }
    }

    /**
     * Clinic sent a consent for signature.
     */
    public function handleConsentSent(ConsentSent $event): void
    {
        try {
            $consent = $event->consent;

            if (!$consent->patient_id || !$consent->clinic_id) {
                return;
            }

            $templateName = $consent->template?->title ?? $consent->template?->name ?? 'Consentimiento';

            PatientPortalNotification::create([
                'clinic_id' => $consent->clinic_id,
                'patient_id' => $consent->patient_id,
                'type' => 'consent_pending',
                'title' => 'Consentimiento pendiente de firma',
                'body' => "Tiene un consentimiento pendiente de firma: {$templateName}.",
                'data' => ['consent_id' => $consent->id],
            ]);
        } catch (Throwable $e) {
            $this->logFailure('consent_pending', $event->consent->id ?? null, $e);
        }
    }

    /**
     * Staff created a payment. Only pending payments notify the patient.
     */
    public function handlePaymentCreated(PaymentCreated $event): void
    {
        try {
            $payment = $event->payment;

            if ($payment->status !== 'pending') {
                return;
            }

            if (!$payment->patient_id || !$payment->clinic_id) {
                return;
            }

            $amount = number_format((float) $payment->amount, 2, ',', '.');

            PatientPortalNotification::create([
                'clinic_id' => $payment->clinic_id,
                'patient_id' => $payment->patient_id,
                'type' => 'payment_pending',
                'title' => 'Pago pendiente',
                'body' => "Tiene un pago pendiente de {$amount} EUR.",
                'data' => ['payment_id' => $payment->id, 'amount' => (float) $payment->amount],
            ]);
        } catch (Throwable $e) {
            $this->logFailure('payment_pending', $event->payment->id ?? null, $e);
        }
    }

    /**
     * A reminder (24h / 2h) was sent for an appointment.
     */
    public function handleAppointmentReminderSent(AppointmentReminderSent $event): void
    {
        try {
            $appointment = $event->appointment;

            if (!$this->isNotifiable($appointment)) {
                return;
            }

            $time = $appointment->start_time ? $appointment->start_time->format('H:i') : '';

            PatientPortalNotification::create([
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $appointment->patient_id,
                'type' => 'appointment_reminder',
                'title' => 'Recordatorio de cita',
                'body' => "Le recordamos su cita a las {$time}.",
                'data' => [
                    'appointment_id' => $appointment->id,
                    'reminder_type' => $event->reminderType,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logFailure('appointment_reminder', $event->appointment->id ?? null, $e);
        }
    }

    private function isNotifiable(?Appointment $appointment): bool
    {
        return $appointment !== null
            && !empty($appointment->patient_id)
            && !empty($appointment->clinic_id);
    }

    private function create(Appointment $appointment, string $type, array $payload): void
    {
        PatientPortalNotification::create(array_merge([
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'type' => $type,
        ], $payload));
    }

    private function formatDate(?Carbon $date): string
    {
        if (!$date) {
            return '';
        }

        return $date->locale('es')->isoFormat('D [de] MMMM [a las] HH:mm');
    }

    private function logFailure(string $type, $resourceId, Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('patient_portal_notification.failed', [
            'event' => 'patient_portal_notification.create',
            'result' => 'failed',
            'type' => $type,
            'resource_id' => $resourceId,
            'error_code' => class_basename($e),
        ]);
    }
}
