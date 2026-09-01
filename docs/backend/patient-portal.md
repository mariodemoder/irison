# Patient Portal — Backend Implementation Guide

| Field | Value |
|-------|-------|
| **Module** | `modules/PatientPortal/` |
| **Framework** | Laravel 12 |
| **Auth** | Sanctum — guard `patient` (model `App\Models\Patient`) |
| **Tenant** | `clinic_id` en todas las queries (aislamiento dual paciente + clínica) |
| **Estado** | Implementado (2026-08-30) |

---

## 1. Estructura real del módulo

```
modules/PatientPortal/
├── Application/
│   ├── DTOs/
│   │   ├── AppointmentRequestDTO.php
│   │   └── ProfileUpdateDTO.php
│   └── Services/
│       ├── PatientAuthService.php
│       ├── PatientDashboardService.php
│       ├── PatientAppointmentService.php
│       ├── PatientBonusService.php
│       ├── PatientPaymentService.php
│       ├── PatientConsentService.php
│       ├── PatientDocumentService.php
│       ├── PatientNotificationService.php
│       └── PatientProfileService.php
├── Domain/
│   ├── Events/
│   │   ├── PatientLoggedIn.php
│   │   ├── PatientAppointmentRequested.php
│   │   ├── PatientAppointmentCancelled.php
│   │   └── PatientConsentSigned.php
│   └── Exceptions/
│       └── AppointmentCancellationDeniedException.php
├── Infrastructure/
│   ├── Controllers/          (11 controllers: 9 recursos + PublicClinicBranding + PatientPortalSettings — validación inline)
│   ├── Middleware/
│   │   ├── PatientAuth.php
│   │   └── PatientClinic.php
│   ├── Listeners/
│   │   └── CreatePatientPortalNotifications.php
│   └── Policies/             (6 policies — documentadas, NO registradas)
└── Routes/
    └── api.php               (requerido desde routes/api.php)
```

> **Nota:** no existe `PatientDashboardDTO`, ni capa `Queries/`, ni `Persistence/`. Las
> consultas son queries Eloquent scoped dentro de los Services. El Dashboard devuelve
> un array tipado.

---

## 2. Migraciones

| Archivo | Tabla | Contenido |
|---|---|---|
| `2026_08_25_000001_add_portal_fields_to_patients_table` | `patients` | `password`, `email_verified_at`, `last_login_at`, `status` (default `inactive`) |
| `2026_08_25_000002_create_patient_portal_notifications_table` | `patient_portal_notifications` | `clinic_id`, `patient_id`, `type`, `title`, `body`, `data`, `read_at` + FKs cascada |
| `2026_08_25_000003_create_patient_audit_logs_table` | `patient_audit_logs` | `clinic_id`, `patient_id`, `event`, `description`, `properties`, `ip_address`, `user_agent` |
| `2026_08_31_000001_create_patient_password_reset_tokens_table` | `patient_password_reset_tokens` | `email` (string PK → guarda el **patient id**, no el email), `token`, `created_at`. Broker `patients` configurado en `config/auth.php` → `passwords.patients.table` |

> **Token de reset por clinic-patient**: `Patient::getEmailForPasswordReset()` devuelve `(string) $this->id` (id globalmente único), por lo que **cada paciente tiene su propio token independiente**. Un email compartido entre varias clínicas ya no colisiona en la clave del token ni invalida el reset de la otra (antes el broker buscaba por `email` y `first()` caía siempre en el paciente de menor id).

Acceso al portal es **opt-in**: la clínica activa el acceso de cada paciente desde el
backoffice (`status = 'active'`). Ver §11 (gestión de acceso desde el panel).

---

## 3. Auth

### 3.1 Configuración

- `config/auth.php`: guard `patient` (driver `sanctum`, provider `patients` → `App\Models\Patient`) y broker `patients` (tabla `patient_password_reset_tokens`, ver §2).
- `app/Models/Patient.php` usa `HasApiTokens`, `Notifiable`, `CanResetPassword` e implementa `CanResetPasswordContract` con override `sendPasswordResetNotification($token)` → `PatientResetPasswordNotification` (URL `/patient/reset-password?...&clinic=` + branding de clínica; ver §12). ✔ Bug B corregido: sin `CanResetPassword`, `forgot/reset` lanzaban 500/TypeError.
- `Patient::getEmailForPasswordReset()` devuelve `(string) $this->id` (per-patient token). ✔ Bug C corregido: el reset/link estaba keyed por email → un email compartido entre clínicas (p.ej. `mariodemoder@gmail.com` en `vito` y `Lopez`) enviaba el email con branding de la otra clínica y cambiaba el password del paciente equivocado.
- `Patient::canUsePortal()` = `status === 'active' && !empty($this->clinic?->slug)` → gate del circuito del portal.
- `bootstrap/app.php`: aliases `patient.auth` y `patient.clinic`.
- Rate limiters (definidos en el propio `Modules/PatientPortal/Routes/api.php`):
  - `patient-login`: 5/min por `email|ip`
  - `patient-forgot`: 3/min por `email`

### 3.2 Middleware del grupo protegido

El grupo protegido usa **tres** middlewares en orden:

```php
Route::middleware(['auth:patient', 'patient.auth', 'patient.clinic'])
```

| Middleware | Función |
|---|---|
| `auth:patient` | Resuelve el token Bearer Sanctum contra el guard `patient`. ✔ Bug A corregido: sin él, el token nunca se resolvía y todas las rutas protegidas daban 401. |
| `patient.auth` | Valida que el autenticado sea `instanceof Patient`, `status === 'active'` y que la clínica tenga `slug != null` (401/403). Las clínicas sin slug quedan fuera de todo el circuito del portal. |
| `patient.clinic` | Exige `clinic_id` y fija `app()->instance('activeClinicId', ...)` para contexto tenant. |

### 3.3 Flujos

- **Login**: valida credenciales con **scope por `clinic.slug`** (si se envía `?clinic=`, el paciente debe pertenecer a esa clínica) → exige `status = 'active'` → **rechaza si la clínica no tiene slug** → revoca tokens previos → crea token con abilities `['patient']` → actualiza `last_login_at` → audit log → `PatientLoggedIn`. Respuesta `patient.clinic` = `{ id, name, slug, logo_url }` (branding para el SPA).
- **Logout**: revoca `currentAccessToken()`.
- **ForgotPassword**: neutral (no enumera emails); usa `Password::broker('patients')->sendResetLink()` con credencial `clinic` escopada por slug. Solo envía si el paciente `$patient->canUsePortal()` (status active + slug presente). El email usa `PatientResetPasswordNotification` → enlace `{APP_FRONTEND_URL}/patient/reset-password?token=...&email={email real}&name={nombre apellido}&clinic={slug}` (NUNCA la ruta de staff `/reset-password`). El email real se usa para pre-rellenar el formulario; el token interno sigue keyed por patient id. El email incluye saludo personalizado "Hola, {Nombre} {Apellido}," y el nombre de la clínica como **título centrado** en el header.
- **ResetPassword**: broker `patients`, scope por `clinic.slug` (Closure en credencial); token es `patient_password_reset_tokens.email = patientId`. El formulario pre-rellena email y muestra saludo "Hola, {Nombre}". Al reset revoca todos los tokens del paciente.
- **Branding público**: `GET /api/patient/public/branding/{clinic.slug}` (sin auth) devuelve `{ name, slug, logo_url }` para las páginas guest (`login`, `forgot`, `reset`). `logo_url` solo si `usesClinicBranding() && hasClinicLogo()`.

---

## 4. Regla de cancelación 24h (T2)

`PatientAppointmentService::cancel()`:

```php
if ($appointment->start_time->lessThan(now()->addHours(24))) {
    throw new AppointmentCancellationDeniedException();
}
$appointment->update(['status' => 'canceled']);
```

- El controller captura `AppointmentCancellationDeniedException` → **403** con mensaje:
  `No es posible cancelar con menos de 24h de antelación. Contacte con la clínica.`
- ✔ **Desviación decidida**: el estado que escribe el portal es **`canceled` (una L)**,
  consistente con el resto de la app (`Appointment::STATUS_CANCELED`). Los tests afirman `canceled`.
- Restaura sesiones de bono si aplica (`restoreBonusUsageIfCancelled`) y despacha `PatientAppointmentCancelled`.

---

## 5. Services — notas de implementación

| Service | Notas |
|---|---|
| `PatientDashboardService` | Agrega próxima cita (scheduled/confirmed futuras), bonos activos/last, pagos pending, consents `sent`, unread notifications. Todo scoped `clinic_id` + `patient_id`. |
| `PatientAppointmentService` | `upcoming/history/show/request/cancel/reschedule`. `request()` crea la cita con `status = 'scheduled'`, `booking_source = 'patient_portal'`; audit log; `PatientAppointmentRequested`. |
| `PatientBonusService` | `index` con `with('bonusType')`; `show` con `with(['bonusType', 'sessionLines.appointmentType', 'usages.appointment'])`. ✔ Bug C corregido: la relación real de `BonusSessionLine` es `appointmentType` (no `bonusType`), que producía 500 con bonos multi-tipo. |
| `PatientPaymentService` | Solo lectura: historial + pendientes. No existe endpoint de pago. |
| `PatientConsentService` | `sign()` delega en `ConsentSignatureService::sign($consent, $signatureSvg, ['ip' => ..., 'user_agent' => ...])`. ✔ Bug D corregido: **no** se pasa `signed_by` (FK a `users`); la firma del paciente queda identificada por `patient_id` + audit log + ip/user_agent, igual que el flujo público `ConsentSignController`. |
| `PatientDocumentService` | `download()` desde disco `private` con path `documents/{clinic_id}/{id}.pdf`; audit log. |
| `PatientNotificationService` | Listado paginado + `markRead`. |
| `PatientProfileService` | `update()` con `ProfileUpdateDTO::toArray()` (filtra nulls) + audit log. |

---

## 6. Autorización: aislamiento dual

Los endpoints **no** usan middleware `can:` ni policies registradas. Cada controller/service
resuelve el recurso con una query scoped doble y `firstOrFail()`:

```php
Appointment::where('clinic_id', $request->user()->clinic_id)
    ->where('patient_id', $request->user()->id)
    ->where('id', $id)
    ->firstOrFail();
```

- Violación de identidad/tenant → **404** (no se filtra existencia del recurso).
- Token de paciente en rutas de backoffice → **401** (guard distinct).
- Token de usuario admin en rutas del portal → **401**.

> **Decisión documentada:** las 6 policies (`PatientAppointmentPolicy`, `PatientBonusPolicy`,
> `PatientPaymentPolicy`, `PatientConsentPolicy`, `PatientDocumentPolicy`,
> `PatientNotificationPolicy`) **existen pero NO se registran** en `AuthServiceProvider`.
> El aislamiento lo garantizan las queries scoped de los servicios y los tests de
> autorización (12 tests IDOR/cross-tenant). Motivo: las policies del backoffice se
> comparten por modelo (`Appointment`, `Bonus`, …) y registrarlas rompería la
> autorización de usuario; la capa de servicios aporta el mismo nivel de control
> sin conflictos de policy por modelo.

---

## 7. Eventos y listener de notificaciones (T3/T4)

### 7.1 Eventos del módulo (`Domain/Events`)

| Evento | Dispatch |
|---|---|
| `PatientLoggedIn` | `PatientAuthService::login()` |
| `PatientAppointmentRequested` | `PatientAppointmentService::request()` |
| `PatientAppointmentCancelled` | `PatientAppointmentService::cancel()` |
| `PatientConsentSigned` | `PatientConsentService::sign()` |

### 7.2 Eventos app-level nuevos

| Evento | Dispatch | Provocador |
|---|---|---|
| `App\Events\PaymentCreated` | `App\Services\Payments\PaymentService::store()` | Alta de pago en backoffice |
| `App\Events\AppointmentReminderSent` | `Modules\Notifications\Domain\Services\ReminderDomainService::sendAppointmentReminder()` | Recordatorio 24h/2h |

### 7.3 Listener central

`Modules\PatientPortal\Infrastructure\Listeners\CreatePatientPortalNotifications` — síncrono,
registrado en `App\Providers\EventServiceProvider`:

| Handler | Evento | Filtro | Notificación |
|---|---|---|---|
| `handleAppointmentUpdated` | `AppointmentUpdated` | solo transición a `status = 'confirmed'` | `appointment_confirmed` |
| `handleAppointmentCancelled` | `AppointmentCancelled` | — | `appointment_cancelled` |
| `handleConsentSent` | `ConsentSent` | `patient_id` + `clinic_id` presentes | `consent_pending` |
| `handlePaymentCreated` | `PaymentCreated` | solo `status = 'pending'` | `payment_pending` |
| `handleAppointmentReminderSent` | `AppointmentReminderSent` | — | `appointment_reminder` |

- Copia `clinic_id`/`patient_id` del recurso fuente (aislamiento tenant + identidad).
- **Nunca propaga errores**: todo handler envuelto en try/catch → `Log::error('patient_portal_notification.failed')`.
- El envío de recordatorio desencadena la notificación **después** del log del reminder.

---

## 8. Rutas (resumen)

```
GET  /api/patient/public/branding/{clinic.slug}   (pública, sin auth)
POST /api/patient/auth/login                  (throttle:patient-login)
POST /api/patient/auth/forgot-password        (throttle:patient-forgot)
POST /api/patient/auth/reset-password
── grupo protegido: auth:patient, patient.auth, patient.clinic ──
POST /api/patient/auth/logout
GET  /api/patient/auth/me
GET  /api/patient/dashboard
GET/PUT /api/patient/profile
GET  /api/patient/appointments/upcoming
GET  /api/patient/appointments/history
GET  /api/patient/appointments/{id}
POST /api/patient/appointments/requests
POST /api/patient/appointments/{id}/cancel
POST /api/patient/appointments/{id}/reschedule
GET  /api/patient/bonuses
GET  /api/patient/bonuses/{id}
GET  /api/patient/payments
GET  /api/patient/payments/pending
GET  /api/patient/consents
GET  /api/patient/consents/{id}
POST /api/patient/consents/{id}/sign
GET  /api/patient/documents
GET  /api/patient/documents/{id}
GET  /api/patient/documents/{id}/download
GET  /api/patient/notifications
POST /api/patient/notifications/{id}/read
```

`routes/api.php` incluye: `require base_path('modules/PatientPortal/Routes/api.php');`

### Rutas de admin (backoffice)

```
PUT /api/patients/{patient}/portal-access        (activar/desactivar acceso — ver §11)
GET  /api/patient-portal/settings                (slug del portal — ver §13)
GET  /api/patient-portal/slug-check?slug=        (disponibilidad del slug — ver §13)
PUT  /api/patient-portal/settings                (guardar slug del portal — ver §13)
```

---

## 9. Frontend

- Router unificado: `resources/js/router/index.js` combina `backofficeRoutes` + `patientRoutes` (`resources/js/router/patient.js`). ✔ T1 corregido: antes solo se montaban las rutas de backoffice y el portal daba `ReferenceError`.
- Layout: `resources/js/layouts/patient/PatientLayout.vue`; vistas en `resources/js/views/patient/*.vue`.
- API client: `resources/js/patient/services/patientApi.js`; auth: `resources/js/patient/composables/usePatientAuth.js` — expone `clinicBranding` y `fetchClinicBranding(slug)`.
- Branding (ver §12): las páginas guest leen `?clinic={slug}` de la URL y pintan logo/nombre de la clínica; el layout autenticado usa `patient.clinic` de `me()`/login.

---

## 10. Registro de actividad (audit)

Tabla `patient_audit_logs` (eventos `patient_*`): login, profile updated, appointment requested,
appointment cancelled, consent signed, document downloaded. Cada entrada guarda
`clinic_id`, `patient_id`, `ip_address`, `user_agent` y `properties`.

---

## 11. Gestión de acceso desde el backoffice (admin)

La activación/desactivación del acceso al portal se gestiona **por la clínica** desde la
ficha del paciente (capa `app/` del backoffice, no del módulo):

### Ruta

```
PUT /api/patients/{patient}/portal-access
Body: { "status": "active" | "inactive" }
```

- Grupo middleware: `auth:sanctum, clinic, check.subscription` (rutas de pacientes).
- Autorización: `Gate::authorize('update', $patient)` → `PatientPolicy::update` =
  `sameClinic && hasOperationalAccess()` (owner/admin/gestor/recepción; viewer/profesional → 403).
- Validación: `app/Http/Requests/Patients/UpdatePatientPortalAccessRequest.php`
  (`status` required, `in:active,inactive`, mensajes ES) → 422 si es inválido.

### Comportamiento (`app/Services/Patients/PatientsServices::setPortalAccess()`)

| Status | Efecto |
|---|---|
| `active` | `patients.status = 'active'` — el paciente ya puede entrar |
| `inactive` | `patients.status = 'inactive'` **y** `$patient->tokens()->delete()` → se revocan todos los tokens Sanctum del paciente y se cierran sus sesiones activas en todos los dispositivos |

- Idempotente: activar/desactivar sobre un estado ya aplicado no da error.
- El login del portal exige `status = 'active'` (middleware `patient.auth`: 401/403).

### Respuesta

200 con el paciente serializado por `mapPatient()`, que ahora incluye:
`portal_status` (`active|inactive`), `has_portal_access` (bool) y `last_login_at`
(null si el paciente nunca entró). `show()` y `index()` del backoffice exponen los mismos campos.

### Auditoría

`ActivityLogger::log` con evento `patient.portal_access.updated` y descripción
"Acceso al portal activado/desactivado" (metadata: entity, entity_id, portal_status).

### Enlace del portal para pacientes

La card "Portal del Paciente" de `resources/js/views/patients/Show.vue` muestra un
enlace copiable `{origin}/patient/login?clinic={clinic.slug}` (slug desde `meClinic`
del SPA staff). Es el enlace que la clínica envía a los pacientes: el `?clinic=` permite
a las páginas guest pintar el branding ANTES de autenticar.

### Tests

`tests/Feature/PatientPortal/PatientPortalAdminTest.php` (8 casos): activar, desactivar +
revocación de tokens, idempotencia, reactivación, 422 status inválido, cross-clinic 404,
viewer 403, guest 401.

### Ayuda ("?") para la clínica en el backoffice

El popup de ayuda reutilizable `resources/js/components/patient/PatientPortalHelpModal.vue`
(explica qué es el portal y cómo gestionarlo paso a paso) está disponible como botón "?"
en dos lugares del backoffice:

- **Ficha del paciente** (`resources/js/views/patients/Show.vue`) → junto al título
  "Portal del Paciente" en la card de acceso. Visible para **cualquier rol**.
- **Servicios → tab "Portal del Paciente"** (`resources/js/views/settings/PatientPortalSettings.vue`)
  → junto al título "Portal del Paciente".

Ya **no** está en el índice de pacientes (`resources/js/views/patients/Index.vue`).

---

## 12. Branding de clínica (el paciente no ve "Irison")

Todo lo que ve el **paciente** (emails + SPA del portal) muestra la identidad de su
clínica, nunca la marca Irison. Las comunicaciones a la clínica (backoffice/suscripción,
staff) mantienen el layout Irison.

### Emails al paciente

| Pieza | Comportamiento |
|---|---|
| `resources/views/emails/partials/email-clinic-header.blade.php` | **Todos** los emails a paciente muestran el **nombre de la clínica como título centrado** en negrita. Si la clínica tiene logo (PRO), el logo se muestra centrado arriba del nombre. Sin clínica (`$clinic` null) → logo Irison (fallback staff/interno). |
| `resources/views/vendor/mail/html/message.blade.php` (footer) | `© año {clinic.name ?? app.name}` — footer con nombre de clínica cuando hay `$clinic` en el theme. |
| From name | Todos los emails a paciente fijan `->from(config('mail.from.address'), $clinic->name)` (dirección global, nombre = clínica): `BookingConfirmation`, `AppointmentCreatedNotification`, `AppointmentUpdatedNotification`, `AppointmentCancelledNotification`, `AppointmentReminderNotification`, `ConsentSignRequestMail`, `PatientResetPasswordNotification`. |

La propagación de `$clinic` al theme usaba el mecanismo ya existente de `viewData`
(`BookingConfirmation` y el resto de notificaciones de paciente) → las vistas publicadas
`resources/views/vendor/notifications/email.blade.php` → `vendor/mail/html/message.blade.php`
→ `vendor/mail/html/header.blade.php` incluyen `email-clinic-header`. El layout
`emails/layouts/irison.blade.php` se usa SOLO en emails de backoffice/suscripción y no se toca.

### Portal SPA (paciente)

- **Guest** (`Login`, `ForgotPassword`, `ResetPassword`): leen `route.query.clinic` →
  `GET /api/patient/public/branding/{slug}` → muestran **nombre de la clínica como título centrado** + logo (si lo hay).
  `ResetPassword` además muestra saludo "Hola, {Nombre}" desde `route.query.name` y pre-rellena el email real.
  Sin `?clinic=` o 404 → header neutro, sin Irison.
- **Autenticado** (`PatientLayout`): usa `patient.clinic` de `me()`/login → **nombre centrado** + logo en la barra superior.
- El `logoini.svg`/`logonameviolet.svg` ya no se usan en superficies de paciente.

### Nota de negocio

El logo se puede subir solo en planes PRO (`usesClinicBranding()`), por lo que las
clínicas basic/trial muestran **solo el nombre**. En todos los emails a paciente,
el nombre de la clínica aparece como **título centrado** en el header (independiente
del plan). `PatientResetPasswordNotification` extiende `ResetPasswordNotificationEs`
(staff intacto: su reset sigue en `/reset-password` con layout Irison) y personaliza
el saludo con el nombre/apellido del paciente.

---

## 13. Configuración del portal (slug de la clínica)

El identificador público del portal del paciente es `clinics.slug`. Se configura desde
el tab "Portal del Paciente" de Servicios (backoffice) y **se auto-genera al registrarse**.

### Auto-generación en el registro

`app/Http/Controllers/API/RegisterController.php`: al crear la clínica se genera
`slug = Str::slug($data['clinic_name'])`, añadiendo `-{4 aleatorios}` si colisiona con
otra clínica. Es **independiente** del slug de booking (`booking_pages.slug`), que ya se
generaba en `bootstrapBooking()`.

### Endpoints staff (grupo `auth:sanctum, clinic, check.subscription`)

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/patient-portal/settings` | Devuelve `{ slug, suggested_slug }`. `suggested_slug` = `Str::slug(clinic.name)` **si** `clinic.slug` es `null` (clínicas creadas antes de la auto-generación) — la UI lo pre-rellena como valor inicial editable. |
| `GET` | `/patient-portal/slug-check?slug=` | `{ available: bool }` comprobando unicidad en `clinics` ignorando la propia. |
| `PUT` | `/patient-portal/settings` | Actualiza `clinic.slug`. Validación: `required`, `max:120`, `regex:/^[a-z0-9-]+$/`, `unique` en `clinics` ignorando la propia. |

Controlador: `modules/PatientPortal/Infrastructure/Controllers/PatientPortalSettingsController.php`.
Autorización: `owner` / perfil `admin` / `manager` (análoga a `CompanyServicesController::authorizeAccess`);
profesionales/recepción → 403.

### Frontend del tab

`resources/js/views/settings/PatientPortalSettings.vue` se monta dentro de
`company-services/Index.vue` (tab "Portal del Paciente"). Expone `save()` (valida el slug
con `slug-check`, hace `PUT /patient-portal/settings` y lanza si no es válido) e integra
con el botón "Guardar" general de Servicios. Muestra la URL pública
`{origin}/patient/login?clinic={slug}` como preview y enlace.

### Tests

`tests/Feature/PatientPortal/PatientPortalSettingsTest.php` (15 casos): lectura owner/admin/manager
y 403 de profesional, sugerencia de slug para clínica sin slug, PUT actualiza slug, validación
formato/colisión/requerido, slug-check available/unavailable/propio-slug/requerido, guest 401.
`modules/Booking/tests/Feature/BookingBootstrapTest.php`: auto-generación + colisión de
`clinic.slug` en el registro.

### Clínicas sin slug quedan fuera del circuito del portal

Las clínicas cuyo `clinics.slug` es `null` (creadas antes de la auto-generación y que aún no
se han configurado) quedan **excluidas de todo el circuito del portal del paciente**:

- `PatientAuthService::login()` responde "El portal del paciente no está disponible para esta clínica."
- `forgotPassword()` **no envía** link salvo que `$patient->canUsePortal()` (status active + slug).
- `resetPassword()` solo aplica para clínica con slug.
- Middleware `patient.auth` → **403** en las rutas autenticadas (defense-in-depth).
- `Patient::canUsePortal()` centraliza el gate: `status === 'active' && !empty($this->clinic?->slug)`.

No se hace backfill de slugs; es decisión activar el portal asignando el slug desde
Servicios → Portal del Paciente.