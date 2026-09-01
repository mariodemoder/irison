# Catálogo completo de notificaciones (Irison)

Módulo Notifications: bounded context bajo `modules/Notifications/` con dos subdominios:

- **Patient/** — Notificaciones de estado de citas (created/updated/cancelled vía `SendAppointmentStatusNotification` listener), email de consentimiento (`SendConsentEmail` listener vía `Mail::to()`).
- **Backoffice/** — Notificaciones de suscripción/facturación (`SubscriptionUpgradeRequestedNotification`, `BackofficeAlertNotification`, `SubscriptionRejectedNotification`, `SubscriptionUpgradedNotification`, `PaymentCompletedNotification`, `CheckoutLinkGeneratedNotification`) con sus listeners.

Eventos: `App\Events\AppointmentCreated`, `AppointmentUpdated`, `AppointmentCancelled` despachados desde `AppointmentService` y `PublicBookingService`.

Modelos clave: `Patient` usa `Notifiable`; `Clinic` tiene `getAdmins()`.

> Recordatorios de citas: `SendAppointmentReminder24hJob` y `SendAppointmentReminder2hJob` se ejecutan con **frecuencia variable** definida por `REMINDER_INTERVAL_MINUTES` (default 15 min, `config/reminders.php`). Detalle: `docs/backend/reminder-scheduling.md`.

## Catálogo de notificaciones

### A Pacientes (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 1 | **BookingConfirmation** | Reserva online | Confirmación con fecha, hora, profesional, clínica + enlace cancelación | Clínica | Email paciente |
| 2 | **AppointmentCreatedNotification** | Cita creada manualmente | Aviso de nueva cita con fecha y hora | Clínica | Email paciente |
| 3 | **AppointmentUpdatedNotification** | Cita modificada | Cambios realizados + nueva fecha/hora | Clínica | Email paciente |
| 4 | **AppointmentCancelledNotification** | Cita cancelada | Cancelación con fecha original | Clínica | Email paciente |
| 5 | **AppointmentReminderNotification** | Job 2h/24h o reenvío manual | Recordatorio: fecha, hora, dirección, teléfono | Clínica | Email paciente |
| 6 | **ConsentSignRequestMail** | Clínica envía consentimiento | Enlace para firmar (expira 72h) | Clínica | Email paciente |
| 7 | **PatientResetPasswordNotification** (paciente) / **ResetPasswordNotificationEs** (staff) | Solicitud restablecer contraseña | Enlace restablecer (expira N min). Paciente → `/patient/reset-password?...&clinic={slug}`, From = clínica; staff → `/reset-password`, From = Irison | Paciente → clínica; staff → Irison | Email usuario |

> **Branding en emails a paciente:** todos los emails al paciente (filas 1-7) muestran el **header con nombre/logo de la clínica** (`emails/partials/email-clinic-header.blade.php`, sin marca de agua Irison), **footer con el nombre de la clínica** (theme `vendor/mail/html/message.blade.php`) y **From name = nombre de la clínica** (dirección global mantenida). Solo las comunicaciones a clínica/backoffice usan el layout Irison de `emails/layouts/irison.blade.php`. Detalle: `docs/backend/patient-portal.md` §12.

### A Clínica/Propietarios (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 8 | **NewOnlineBooking** | Nueva reserva online | Datos paciente (nombre, email, teléfono), fecha, hora, notas | Irison | Owners clínica |
| 9 | **SubscriptionActivatedMail** | Nueva suscripción activada | Bienvenida, plan, fecha activación, enlace factura | Irison | Owner/admin |
| 10 | **CheckoutLinkGeneratedNotification** | Enlace de pago generado para upgrade | Enlace Stripe para completar pago | Irison | Owner/admin |
| 11 | **PaymentCompletedNotification** | Pago de upgrade completado | Confirmación de pago, plan actualizado | Irison | Owner/admin |
| 12 | **SubscriptionUpgradedNotification** | Upgrade completado | Plan actualizado, bienvenida | Irison | Owner/admin |
| 13 | **SubscriptionUpgradedNotificationMail** | (Fallback) Confirmación upgrade | Mismo contenido que #12, enviado directo desde controlador | Irison | Owner/admin |
| 14 | **InvoicePaymentFailedMail** | Pago factura falló (webhook) | Aviso pago pendiente, monto, próximo intento | Irison | Email clínica + owner |
| 15 | **ResendInvoiceMail** | Admin reenvía factura | Enlace factura + mensaje personalizado | Irison | Email destinatario |
| 21 | **SubscriptionRejectedNotification** | Upgrade rechazado | Rechazo con comentarios del admin, plan solicitado | Irison | Email owner |

### Internas / Backoffice (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 16 | **SubscriptionCanceledInternalMail** | Suscripción cancelada | Datos clínica + IDs Stripe | Irison | `cancellation_notification_to` |
| 17 | **ContactMail** | Formulario de contacto | Nombre, email, asunto, mensaje | Usuario | `CONTACT_EMAIL` |
| 18 | **AccountActivationMail** | Nuevo registro | Enlace activar cuenta + trial | Irison | Email registrado |
| 19 | **TrialLifecycleMail** | Hitos trial (día 1,7,20,27,30) | Mensajes según milestone, enlace facturación | Irison | Email owner |

### Solo Database (bandeja interna)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 20 | **SubscriptionUpgradeRequestedNotification** | Solicitud upgrade de plan | Plan solicitado, clínica, solicitante | Sistema | Admins clínica |
| 22 | **BackofficeAlertNotification** | Alertas internas de suscripción (`backoffice_upgrade_requested`, `trial_expired`, `trial_converted`, `subscription_cancelled`) | `type`, clínica, mensaje + extras según tipo | Sistema | Admins backoffice activos |

> Nota: `BackofficeAlertNotification` se reconcilia en cada carga del índice de clínicas (`ClinicController@index` → `BackofficeAlertService::reconcileMany`). Es idempotente (dedupe `type|clinic_id|admin_id`) y deriva alertas del estado actual, sin backfill retroactivo.

## Tests y formato

- Tests: `tests/Feature/Mail/EmailDispatchTest.php` y `tests/Feature/Notifications/NotificationsTest.php`.
- Formato de email: todos los emails de Irison hacia subscriptores usan el layout unificado `emails/layouts/irison.blade.php` (header con logo Irison + pie legal genérico). Detalle: `docs/qa/email-tests.md`.
- Comportamiento: `docs/backoffice/subscriptions.md` (ciclo de suscripción) y `docs/backoffice/notificaciones-internas.md` (alertas internas de backoffice).