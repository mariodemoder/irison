# Email Dispatch Tests — QA Guide

## Ejecutar todos los tests de email

```bash
php artisan test --filter=EmailDispatchTest
```

Salida esperada: **24 passed** (37 assertions)

---

## Categorías de tests

### 1. Notifications — envío (4 tests)

Verifican que las notificaciones se envían correctamente por el canal `mail`.

| Test | Notificación | Disparador |
|------|-------------|------------|
| `test_reset_password_notification_is_sent` | `ResetPasswordNotificationEs` | Usuario solicita restablecer contraseña |
| `test_checkout_link_generated_notification_is_sent` | `CheckoutLinkGenerated` | Se crea sesión de checkout para upgrade |
| `test_booking_confirmation_notification_is_sent` | `BookingConfirmation` | Paciente crea reserva online |
| `test_new_online_booking_notification_is_sent` | `NewOnlineBooking` | Nueva reserva online notifica al clinic |

### 2. Notifications — logo Irison (4 tests)

Verifican que las notificaciones (MailMessage) renderizan el logo de Irison en el header y **NO** el logo de Laravel.

| Test | Notificación |
|------|-------------|
| `test_reset_password_notification_renders_irison_logo` | `ResetPasswordNotificationEs` |
| `test_checkout_link_generated_notification_renders_irison_logo` | `CheckoutLinkGenerated` |
| `test_booking_confirmation_notification_renders_irison_logo` | `BookingConfirmation` |
| `test_new_online_booking_notification_renders_irison_logo` | `NewOnlineBooking` |

> Estas notificaciones usan el layout Markdown de mail (`<x-mail::message>`) que incluye el componente `<x-mail::header>`. El logo se controla desde `resources/views/vendor/mail/html/header.blade.php`.

### 3. Mailables — contenido (16 tests)

Verifican que cada Mailable renderiza sin errores y contiene el contenido esperado.

| Test | Mailable | Descripción |
|------|----------|-------------|
| `test_account_activation_mail_renders_and_contains_expected_content` | `AccountActivationMail` | Email de activación tras registro |
| `test_subscription_request_mail_renders_and_contains_expected_content` | `SubscriptionRequestMail` | Solicitud interna de upgrade |
| `test_subscription_status_mail_approved_renders` | `SubscriptionStatusMail` | Upgrade aprobado |
| `test_subscription_status_mail_rejected_renders` | `SubscriptionStatusMail` | Upgrade rechazado |
| `test_upgrade_checkout_link_mail_renders` | `UpgradeCheckoutLinkMail` | Link de pago para upgrade |
| `test_payment_completed_mail_renders` | `PaymentCompletedMail` | Confirmación de pago completado |
| `test_subscription_canceled_internal_mail_renders` | `SubscriptionCanceledInternalMail` | Cancelación interna de suscripción |
| `test_contact_mail_renders` | `ContactMail` | Mensaje de contacto/soporte |
| `test_invoice_payment_failed_mail_renders` | `InvoicePaymentFailedMail` | Fallo en cobro de factura |
| `test_consent_sign_request_mail_renders` | `ConsentSignRequestMail` | Solicitud de firma de consentimiento |
| `test_appointment_reminder_mail_renders` | `AppointmentReminderMail` | Recordatorio de cita (24h/2h) |
| `test_trial_lifecycle_mail_day_1_renders` | `TrialLifecycleMail` | Trial día 1 — Bienvenida |
| `test_trial_lifecycle_mail_day_7_renders` | `TrialLifecycleMail` | Trial día 7 — Tips |
| `test_trial_lifecycle_mail_day_20_renders` | `TrialLifecycleMail` | Trial día 20 — Termina pronto |
| `test_trial_lifecycle_mail_day_27_renders` | `TrialLifecycleMail` | Trial día 27 — Últimos días |
| `test_trial_lifecycle_mail_day_30_renders` | `TrialLifecycleMail` | Trial día 30 — Límite |

> Los Mailables usan sus propias plantillas Blade (no el layout Markdown), por lo que el logo del header no aplica. Cada plantilla tiene su propio diseño.

---

## Archivos relacionados

| Archivo | Propósito |
|---------|-----------|
| `tests/Feature/Mail/EmailDispatchTest.php` | Suite completa de tests |
| `resources/views/vendor/mail/html/header.blade.php` | Header custom con logo Irison |
| `public/logo.svg` | Logo de Irison para emails |

---

## Ejecutar tests de email junto con otros tests

```bash
# Solo tests de email
php artisan test --filter=EmailDispatchTest

# Tests de email + reminders (relacionados)
php artisan test --filter="EmailDispatchTest|ReminderNotificationsTest"

# Todos los tests del proyecto
php artisan test
```

---

## Fix del logo de Laravel

**Problema:** Las notificaciones usaban el logo de Laravel por defecto.

**Solución:** Se publicó `resources/views/vendor/mail/html/header.blade.php` sobreescribiendo el componente header del Markdown mail. Cuando el slot no es "Laravel", el componente mostraba solo texto. Ahora siempre muestra el logo de Irison via `asset('logo.svg')`.

**Cambio en `header.blade.php`:**
```blade
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('logo.svg') }}" alt="Irison" style="height: 50px;">
</a>
</td>
</tr>
```
