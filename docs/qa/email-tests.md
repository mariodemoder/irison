# Email Dispatch Tests — QA Guide

## Formato unificado de emails de Irison (suscripción/facturación)

Los emails que Irison emite hacia los subscriptores (owners de clínicas) comparten un único formato visual definido en:

- **Layout:** `resources/views/emails/layouts/irison.blade.php`
- **Header:** `resources/views/emails/partials/email-header.blade.php` (logo Irison centrado)
- **Footer legal:** `resources/views/emails/partials/email-footer.blade.php` (copyright, email de contacto y web, sin datos de empresa)
- **Botón CTA:** `resources/views/emails/partials/email-cta.blade.php` (azul primario de la app `#2563EB/#1d4ed8`)

### Tokens de diseño

| Token | Valor |
|---|---|
| Fondo exterior | `#f6f8fc` |
| Tarjeta | 620px, radius 16px, sombra `0 10px 28px rgba(15,23,42,.08)` |
| Fuente | `Segoe UI, Arial, sans-serif` |
| H1 | 22px `#020617` |
| Cuerpo | 15px `#334155`, line-height 1.6 |
| Tabla informativa | borde `#e2e8f0`, radius 12px, label `#f8fafc`/`#475569`, valor 700 `#0f172a` |
| CTA | gradiente `linear-gradient(135deg,#0ea5e9,#1d4ed8)`, radius 10px, texto blanco 14px/700 |

### Plantillas unificadas

`subscription-activated`, `subscription-upgraded-notification`, `payment-completed`, `upgrade-checkout-link`, `subscription-status`, `reactivation-status`, `invoice-payment-failed`, `resend-invoice`, `trial-lifecycle`, `account-activation` — todas usan `@extends('emails.layouts.irison')` y heredan header + footer.

> Nota: los emails a pacientes (recordatorios, consentimientos, reservas online) usan branding de la clínica (`email-clinic-header`) y los internos (contact, cancelación) no forman parte de este formato.

---

## Ejecutar todos los tests de email

```bash
php artisan test --filter=EmailDispatchTest
```

Salida esperada: **24 passed** (46 assertions)

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
| `resources/views/vendor/mail/html/header.blade.php` | Header custom con logo Irison (layout Markdown) |
| `resources/views/emails/layouts/irison.blade.php` | Layout unificado de emails Irison → subscriptores |
| `resources/views/emails/partials/email-footer.blade.php` | Pie legal genérico |
| `resources/views/emails/partials/email-cta.blade.php` | Botón CTA estándar azul |
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
