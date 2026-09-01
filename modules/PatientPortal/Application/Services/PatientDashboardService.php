<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\Payment;
use App\Models\PatientConsent;
use App\Models\PatientPortalNotification;
use App\Models\Patient;

class PatientDashboardService
{
    public function getForPatient(Patient $patient): array
    {
        $clinicId = $patient->clinic_id;
        $patientId = $patient->id;

        $nextAppointment = Appointment::where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('start_time', '>', now())
            ->with(['professional', 'appointmentType'])
            ->orderBy('start_time')
            ->first();

        $bonuses = Bonus::where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->get();

        $activeBonuses = $bonuses->filter(fn($b) => in_array($b->status, ['active', 'last']));

        $pendingPayments = Payment::where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('status', 'pending')
            ->get();

        $pendingConsents = PatientConsent::where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('status', 'sent')
            ->get();

        $unreadNotifications = PatientPortalNotification::where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->whereNull('read_at')
            ->count();

        return [
            'patient' => ['first_name' => $patient->first_name, 'last_name' => $patient->last_name],
            'next_appointment' => $nextAppointment ? [
                'id' => $nextAppointment->id,
                'start_time' => $nextAppointment->start_time,
                'professional_name' => $nextAppointment->professional->name ?? 'N/A',
                'service_name' => $nextAppointment->appointmentType->name ?? $nextAppointment->custom_type ?? 'N/A',
                'status' => $nextAppointment->status,
            ] : null,
            'bonuses_summary' => [
                'active_count' => $activeBonuses->count(),
                'total_remaining_sessions' => $activeBonuses->sum('remaining_sessions'),
                'expiring_soon' => $activeBonuses->filter(fn($b) =>
                    $b->expires_at && $b->expires_at->diffInDays(now()) <= 30
                )->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'remaining_sessions' => $b->remaining_sessions,
                    'expires_at' => $b->expires_at->format('Y-m-d'),
                ])->values(),
            ],
            'pending_payments' => [
                'count' => $pendingPayments->count(),
                'total_amount' => round($pendingPayments->sum('amount'), 2),
            ],
            'pending_consents' => [
                'count' => $pendingConsents->count(),
                'items' => $pendingConsents->map(fn($c) => [
                    'id' => $c->id,
                    'template_name' => $c->template->title ?? 'Consentimiento',
                    'sent_at' => $c->sent_at?->format('Y-m-d'),
                ])->values(),
            ],
            'notifications' => ['unread_count' => $unreadNotifications],
        ];
    }
}
