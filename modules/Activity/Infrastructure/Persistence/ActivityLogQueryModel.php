<?php

declare(strict_types=1);

namespace Modules\Activity\Infrastructure\Persistence;

use App\Models\ActivityLog;

class ActivityLogQueryModel extends ActivityLog
{
    protected $table = 'activity_logs';

    public const ENTITY_PATIENT = 'patient';
    public const ENTITY_APPOINTMENT = 'appointment';
    public const ENTITY_PAYMENT = 'payment';
    public const ENTITY_CONSENT = 'consent';
    public const ENTITY_DOCUMENT = 'document';

    public const EVENTS = [
        'patient.created' => 'Paciente creado',
        'patient.updated' => 'Paciente modificado',
        'patient.deleted' => 'Paciente eliminado',
        'appointment.created' => 'Cita creada',
        'appointment.updated' => 'Cita modificada',
        'appointment.canceled' => 'Cita cancelada',
        'appointment.deleted' => 'Cita eliminada',
        'payment.created' => 'Pago registrado',
        'payment.updated' => 'Pago modificado',
        'consent.sent' => 'Consentimiento enviado',
        'consent.signed' => 'Consentimiento firmado',
        'consent.revoked' => 'Consentimiento revocado',
        'document_created' => 'Documento creado',
        'login' => 'Inicio de sesión',
    ];
}
