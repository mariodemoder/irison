# Patient Portal — Specification

| Field | Value |
|-------|-------|
| **Version** | 1.2.0 |
| **Date** | 2026-08-25 (actualizado 2026-08-31) |
| **Status** | **Implemented** |
| **Module** | `modules/PatientPortal/` |

> **Estado: Implementado.** La implementación real se documenta en
> `docs/backend/patient-portal.md`. Este spec conserva el diseño original y
> marca las desviaciones decididas en la sección 22.

---

## 1. Objective

Deliver a secure, mobile-first **Patient Portal** that allows patients to self-service access their appointments, bonuses, payments, consents, documents, notifications, and profile — fully isolated from the backoffice and from other patients.

The portal operates within the same Laravel 12 + Vue 3 SPA as the backoffice but uses a **separate authentication guard**, dedicated route prefix (`/patient/`), dedicated layout, and patient-specific policies that enforce tenant and identity isolation.

**Out of scope for this phase:**
- Stripe / online payment processing (payments are manual — staff marks as paid)
- Full clinical history access
- WhatsApp / SMS notifications
- Multi-language support

---

## 2. Architecture

### 2.1 Mini DDD Module Structure

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
│   ├── Controllers/          (11 controllers; validación inline, sin FormRequests)
│   ├── Middleware/
│   │   ├── PatientAuth.php
│   │   └── PatientClinic.php
│   ├── Listeners/
│   │   └── CreatePatientPortalNotifications.php
│   └── Policies/             (6 policies — existen, NO registradas, ver §22)
└── Routes/
    └── api.php
```

> **Desviación 22.1:** no existe `PatientDashboardDTO`, ni capa `Queries/`, ni
> `PatientNotFoundException`, ni `Persistence/`. Las consultas son queries Eloquent
> scoped dentro de los Services. Añadido `Listeners/` (T4).

### 2.2 Existing Domain Integration

The Patient Portal does **not** duplicate domain models. It queries the existing Eloquent models through its own scoped queries and policies:

| Portal Feature | Existing Model | Relation |
|---|---|---|
| Dashboard | `Patient`, `Appointment`, `Bonus`, `Payment`, `PatientConsent` | `patient_id` + `clinic_id` |
| Appointments | `Appointment` | `patient_id` + `clinic_id` + `status` |
| Bonuses | `Bonus` | `patient_id` + `clinic_id` |
| Payments | `Payment` | `patient_id` + `clinic_id` |
| Consents | `PatientConsent` | `patient_id` + `clinic_id` |
| Documents | `Document` | `patient_id` + `clinic_id` |
| Notifications | `PatientPortalNotification` (new) | `patient_id` + `clinic_id` |

---

## 3. Tenant & Security Model

### 3.1 Isolation Rules

Every patient-facing query enforces **dual isolation**:

1. **Patient identity**: `resource.patient_id = authenticated_patient.id`
2. **Tenant boundary**: `resource.clinic_id = authenticated_patient.clinic_id`

Both conditions must hold. Violations return `403 Forbidden` (authorization failure) or `404 Not Found` (resource not found for this patient).

### 3.2 Authentication Separation

| Concern | Backoffice | Patient Portal |
|---|---|---|
| Guard | `sanctum` (User model) | `patient` (Patient model) |
| Token model | `personal_access_tokens` (tokenable = User) | `personal_access_tokens` (tokenable = Patient) |
| Route prefix | `/api/` | `/api/patient/` |
| Middleware | `auth:sanctum` | `patient.auth` |
| Rate limiting | Standard | Stricter (login: 5/min) |

### 3.3 No Cross-Access

- A patient **cannot** access any `/api/` backoffice endpoint
- A backoffice user **cannot** authenticate via the patient guard
- Patient tokens are invalid for backoffice endpoints and vice versa

---

## 4. Patient Authentication

### 4.1 Endpoints

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| `POST` | `/api/patient/auth/login` | Public | Login with email + password → Sanctum token |
| `POST` | `/api/patient/auth/logout` | Protected | Revoke current token |
| `POST` | `/api/patient/auth/forgot-password` | Public | Send password reset email |
| `POST` | `/api/patient/auth/reset-password` | Public | Reset password with token |
| `GET` | `/api/patient/auth/me` | Protected | Return authenticated patient profile |

### 4.2 Login Flow

1. Validate `email` + `password` against `patients` **scoped by `clinic.slug`** (si `POST` lleva `?clinic=`, el paciente debe pertenecer a esa clínica)
2. Check `status = 'active'` — inactive patients rejected with 403
3. **Rechaza si la clínica no tiene slug** ("El portal del paciente no está disponible para esta clínica.") — las clínicas sin slug quedan fuera de todo el circuito
4. Create Sanctum personal access token (abilities: `['patient']`)
5. Update `last_login_at` timestamp
6. Dispatch `PatientLoggedIn` event (audit log)
7. Return token + patient profile

### 4.3 Password Reset Flow

1. `POST /api/patient/auth/forgot-password` with `email` (+ opcional `clinic` slug)
2. Si existe un paciente con ese email **en la clínica scoped** y `canUsePortal()` (status active + slug) → send `PatientResetPasswordNotification` (extiende `ResetPasswordNotificationEs` pero apunta a `/patient/reset-password?token=...&email={email real}&name={nombre apellido}&clinic={slug}` del SPA paciente; ver 22.12). El email incluye saludo personalizado "Hola, {Nombre} {Apellido}," y el nombre de la clínica como título centrado en el header.
3. Si paciente no encontrado / no puede usar el portal → neutral response (no email enumeration)
4. `POST /api/patient/auth/reset-password` with `token`, `email`, `password`, `password_confirmation`
5. Validate token (**per-patient**: `patient_password_reset_tokens.email` = patient id; tabla dedicada, broker `patients`), hash new password, update `patients.password`
6. Revoke all existing patient tokens for security

> **Scoping por clinic-patient**: la causa de los bugs de reset era un broker que buscaba solo por
> `email` y `first()` caía en el paciente de menor id (email compartido entre clínicas). Ahora:
> (1) `Patient::getEmailForPasswordReset()` = `(string) $this->id` → token **independiente por paciente**;
> (2) `forgot`/`reset`/`login` escopan por `clinic.slug` vía credencial Closure en el broker y
> `whereHas('clinic', slug)` en el lookup. La URL del email lleva el **email real** (para pre-rellenar
> el formulario) + `name={nombre apellido}` (para el saludo), From name = clínica, y el nombre de la
> clínica se muestra como título centrado en el header del email. Solo cambia el password de esa clínica.

### 4.4 Token Configuration

- Token name: `"Patient Portal"` (identifiable in token list)
- Abilities: `['patient']`
- No expiration by default (patient can stay logged in)
- Logout revokes the current token only

---

## 5. Patient Account Fields

### 5.1 Existing Fields (from `patients` table)

| Field | Type | Portal Access |
|-------|------|---------------|
| `id` | bigint | Internal (not exposed in API) |
| `clinic_id` | bigint | Internal |
| `first_name` | string | **Read + Write** (profile) |
| `last_name` | string | **Read + Write** (profile) |
| `phone` | string | **Read + Write** (profile) |
| `email` | string | **Read** (display only, change requires clinic) |
| `birth_date` | date | **Read only** (requires clinic approval to change) |
| `notes` | text | Internal (not exposed) |
| `nif` | string | Internal |
| `address` | string | **Read + Write** (profile) |
| `zip` | string | **Read + Write** (profile) |
| `city` | string | **Read + Write** (profile) |
| `province` | string | **Read + Write** (profile) |
| `country` | string | **Read + Write** (profile) |
| `counter` | string | Internal |
| `created_at` | timestamp | Internal |
| `updated_at` | timestamp | Internal |
| `deleted_at` | timestamp | Internal |

### 5.2 New Fields (migration)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `password` | string (hashed) | `null` | Bcrypt hash; `null` = no portal access yet |
| `email_verified_at` | timestamp (nullable) | `null` | Set on first login or activation |
| `last_login_at` | timestamp (nullable) | `null` | Updated on each login |
| `status` | string (enum) | `'inactive'` | `inactive`, `active`, `suspended` |

### 5.3 Account Activation

When a clinic creates a patient, portal access is **inactive** by default. To activate:

1. Staff sets `status = 'active'` and generates a password (or the patient uses forgot-password)
2. Patient receives email with login instructions
3. Patient logs in → `email_verified_at` is set automatically on first successful login
4. Patient can now access the portal

---

## 6. Dashboard

**Endpoint:** `GET /api/patient/dashboard`

### 6.1 Response Structure

```json
{
  "patient": {
    "first_name": "María",
    "last_name": "García"
  },
  "next_appointment": {
    "id": 42,
    "start_time": "2026-09-01T10:00:00Z",
    "professional_name": "Dr. López",
    "service_name": "Consulta general",
    "status": "scheduled"
  },
  "bonuses_summary": {
    "active_count": 2,
    "total_remaining_sessions": 8,
    "expiring_soon": [
      {
        "id": 15,
        "name": "Pack Fisioterapia",
        "remaining_sessions": 2,
        "expires_at": "2026-09-10"
      }
    ]
  },
  "pending_payments": {
    "count": 1,
    "total_amount": 45.00
  },
  "pending_consents": {
    "count": 1,
    "items": [
      {
        "id": 3,
        "template_name": "Consentimiento Informado",
        "sent_at": "2026-08-20"
      }
    ]
  },
  "notifications": {
    "unread_count": 3
  }
}
```

### 6.2 Data Sources

| Section | Query | Filter |
|---------|-------|--------|
| Next appointment | `Appointment::where(patient_id, clinic_id)` | `status in (scheduled, confirmed)`, `start_time > now()`, `orderBy('start_time')`, `first()` |
| Bonuses summary | `Bonus::where(patient_id, clinic_id)` | filtro `status in (active, last)` sobre el listado completo |
| Pending payments | `Payment::where(patient_id, clinic_id)` | `status = 'pending'` |
| Pending consents | `PatientConsent::where(patient_id, clinic_id)` | `status = 'sent'` (not yet signed) |
| Notifications | `PatientPortalNotification::where(patient_id, clinic_id)` | `read_at = null` |

---

## 7. Appointments

### 7.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/appointments/upcoming` | Future appointments (scheduled, confirmed) |
| `GET` | `/api/patient/appointments/history` | Past + filtered appointments |
| `GET` | `/api/patient/appointments/{id}` | Single appointment detail |
| `POST` | `/api/patient/appointments/requests` | Request a new appointment (not direct booking) |
| `POST` | `/api/patient/appointments/{id}/cancel` | Cancel with 24h policy |
| `POST` | `/api/patient/appointments/{id}/reschedule` | Request reschedule |

### 7.2 Statuses

| Status | Meaning | Patient Can See |
|--------|---------|-----------------|
| `scheduled` | Booked, awaiting confirmation | Yes |
| `confirmed` | Confirmed by clinic | Yes |
| `completed` | Appointment finished | Yes (history) |
| `cancelled` | Cancelled by patient or clinic | Yes (history) |
| `no_show` | Patient didn't attend | Yes (history) |
| `rescheduled` | Moved to new time | Yes (history) |

### 7.3 Appointment Request (POST /api/patient/appointments/requests)

Patients do **not** directly book appointments. They submit a request:

```json
{
  "preferred_date": "2026-09-05",
  "preferred_time": "10:00",
  "professional_id": 5,
  "service_name": "Consulta general",
  "notes": "Dolor en rodilla derecha"
}
```

The system:
1. Creates an `Appointment` with `status = 'scheduled'` and `booking_source = 'patient_portal'`
2. Sends a notification to the clinic (`NewOnlineBooking` notification pattern)
3. Returns the created appointment to the patient

### 7.4 Cancellation Policy

- Patient can cancel if `start_time` is **at least 24 hours** in the future
- Cancellation within 24h returns `403` with message: "No es posible cancelar con menos de 24h de antelación. Contacte con la clínica."
- Cancellation sets `status = 'canceled'` ✔ **Desviación 22.2:** el portal escribe **`canceled` (una L)**, el estado canónico de la app; el borrador decía `cancelled`.
- If bonus was applied, sessions are restored via `restoreBonusUsageIfCancelled()` (roba la lógica existente de `Bonus`)
- Dispatches `PatientAppointmentCancelled` event

### 7.5 Reschedule

- Creates a new appointment request linked to the original
- Sets original appointment `status = 'rescheduled'`
- New request follows the same flow as appointment request

### 7.6 Filters (History)

| Parameter | Type | Description |
|-----------|------|-------------|
| `from` | date | Start date (inclusive) |
| `to` | date | End date (inclusive) |
| `status` | string | Filter by status |
| `professional_id` | int | Filter by professional |
| `service` | string | Filter by service name/type |
| `page` | int | Pagination (default 1) |
| `per_page` | int | Items per page (default 15, max 50) |

---

## 8. Bonuses / Packages

### 8.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/bonuses` | List all bonuses (active, exhausted, expired) |
| `GET` | `/api/patient/bonuses/{id}` | Single bonus detail with session lines |

### 8.2 Response Structure

**List:**
```json
{
  "bonuses": [
    {
      "id": 15,
      "name": "Pack Fisioterapia 10 sesiones",
      "total_sessions": 10,
      "remaining_sessions": 3,
      "status": "active",
      "expires_at": "2026-12-31",
      "created_at": "2026-06-01"
    }
  ]
}
```

**Detail:**
```json
{
  "bonus": {
    "id": 15,
    "name": "Pack Fisioterapia 10 sesiones",
    "total_sessions": 10,
    "remaining_sessions": 3,
    "status": "active",
    "expires_at": "2026-12-31",
    "session_lines": [
      {
        "service_name": "Fisioterapia",
        "total_quantity": 6,
        "remaining_quantity": 2
      },
      {
        "service_name": "Electroterapia",
        "total_quantity": 4,
        "remaining_quantity": 1
      }
    ],
    "usage_history": [
      {
        "used_at": "2026-08-15T10:00:00Z",
        "appointment_date": "2026-08-15",
        "service_name": "Fisioterapia"
      }
    ]
  }
}
```

### 8.3 Status Computation

Status is computed server-side from `remaining_sessions` and `expires_at`:

| Condition | Status |
|-----------|--------|
| `expires_at < now()` | `expired` |
| `remaining_sessions = 0` | `exhausted` |
| `remaining_sessions = 1` | `last` |
| Otherwise | `active` |

---

## 9. Payments

### 9.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/payments` | Full payment history |
| `GET` | `/api/patient/payments/pending` | Only pending payments |

### 9.2 Response Structure

```json
{
  "payments": [
    {
      "id": 101,
      "concept": "Consulta general",
      "amount": 45.00,
      "method": "cash",
      "status": "completed",
      "paid_at": "2026-08-20T10:30:00Z",
      "appointment_date": "2026-08-20"
    }
  ],
  "summary": {
    "total_paid": 250.00,
    "pending_amount": 45.00
  }
}
```

### 9.3 Payment Statuses

| Status | Patient Sees |
|--------|-------------|
| `pending` | "Pendiente de pago" |
| `completed` | "Pagado" |
| `refunded` | "Reembolsado" |

### 9.4 No Payment Processing

There is **no endpoint** for patients to process payments. Payments are managed manually by staff. The portal is **read-only** for payment data.

---

## 10. Consents

### 10.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/consents` | List all consents (pending + signed) |
| `GET` | `/api/patient/consents/{id}` | Single consent detail (content + signature status) |
| `POST` | `/api/patient/consents/{id}/sign` | Sign a pending consent |

### 10.2 Consent Status

| Status | Patient Sees |
|--------|-------------|
| `sent` | "Pendiente de firma" |
| `signed` | "Firmado el {date}" |
| `revoked` | "Revocado el {date}" |

### 10.3 Sign Flow

1. Patient views consent content (`content_html`)
2. Patient draws signature (canvas → SVG)
3. `POST /api/patient/consents/{id}/sign` with `signature_svg`
4. System:
   - Validates consent is in `sent` status
   - Calls `ConsentSignatureService::sign()` (reuse existing service) pasando `['ip' => ..., 'user_agent' => ...]`
   - Sets `signature_svg`, `signed_at`, `ip`, `user_agent`. ✔ **Desviación 22.3:** **no** se setea `signed_by = patient.id` — `signed_by` es FK a `users` (staff); la firma del paciente queda identificada por `patient_id` + audit log + ip/user_agent (idéntico al flujo público `ConsentSignController`).
   - Sets `hash` for integrity verification
   - Dispatches `PatientConsentSigned` event
   - Generates signed PDF (stored in private storage)

### 10.4 Data Preserved

- `patient_id`, `clinic_id`, `template_id`, `template_version`
- `signed_at`, `ip`, `user_agent`
- `signature_svg`, `hash`
- `content_html` (snapshot at signing time)
- Generated PDF in private storage

---

## 11. Documents

### 11.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/documents` | List documents shared by clinic |
| `GET` | `/api/patient/documents/{id}` | Download authorized document |

### 11.2 Document Types Visible to Patient

Only documents with `patient_id` matching the authenticated patient:

| Type | Description |
|------|-------------|
| `invoice` | Invoices related to the patient's appointments/packages |
| `abono` | Payment receipts |

### 11.3 Download

- Returns file stream with appropriate `Content-Type` and `Content-Disposition` headers
- No public URLs — private storage only
- Logs download event for audit

---

## 12. Notifications

### 12.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/notifications` | List notifications (newest first) |
| `POST` | `/api/patient/notifications/{id}/read` | Mark as read |

### 12.2 Notification Types

| Type | Trigger | Content |
|------|---------|---------|
| `appointment_confirmed` | Clinic confirms appointment | "Su cita del {date} ha sido confirmada" |
| `appointment_reminder` | 24h/2h reminder | "Le recordamos su cita mañana a las {time}" |
| `consent_pending` | Clinic sends consent | "Tiene un consentimiento pendiente de firma" |
| `payment_pending` | Staff creates pending payment | "Tiene un pago pendiente de {amount}" |
| `appointment_cancelled` | Clinic cancels appointment | "Su cita del {date} ha sido cancelada" |

### 12.3 Notification Storage

Uses the `patient_portal_notifications` table:

```sql
CREATE TABLE patient_portal_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    data JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_patient_clinic (clinic_id, patient_id),
    INDEX idx_unread (patient_id, read_at)
);
```

---

## 13. Profile

### 13.1 Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/patient/profile` | Get profile data |
| `PUT` | `/api/patient/profile` | Update profile (limited fields) |

### 13.2 Updatable Fields

| Field | Updatable | Notes |
|-------|-----------|-------|
| `first_name` | Yes | |
| `last_name` | Yes | |
| `phone` | Yes | |
| `address` | Yes | |
| `zip` | Yes | |
| `city` | Yes | |
| `province` | Yes | |
| `country` | Yes | |
| `email` | **No** | Requires clinic approval |
| `birth_date` | **No** | Requires clinic approval |
| `nif` | **No** | Identity document, requires clinic approval |

### 13.3 Update Flow

1. Validate updatable fields only
2. Update patient record
3. Dispatch profile change audit event
4. Return updated profile

---

## 14. API Structure

All patient routes live under `/api/patient/`. El grupo protegido usa
**`auth:patient`** (resolución del token Sanctum contra el guard `patient`) +
`patient.auth` + `patient.clinic`:

```php
Route::middleware(['auth:patient', 'patient.auth', 'patient.clinic'])->group(function () {

    // Auth
    Route::post('auth/logout', [PatientAuthController::class, 'logout']);
    Route::get('auth/me', [PatientAuthController::class, 'me']);

    // Dashboard
    Route::get('dashboard', [PatientDashboardController::class, 'index']);

    // Profile
    Route::get('profile', [PatientProfileController::class, 'index']);
    Route::put('profile', [PatientProfileController::class, 'update']);

    // Appointments
    Route::get('appointments/upcoming', [PatientAppointmentController::class, 'upcoming']);
    Route::get('appointments/history', [PatientAppointmentController::class, 'history']);
    Route::get('appointments/{id}', [PatientAppointmentController::class, 'show']);
    Route::post('appointments/requests', [PatientAppointmentController::class, 'request']);
    Route::post('appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel']);
    Route::post('appointments/{id}/reschedule', [PatientAppointmentController::class, 'reschedule']);

    // Bonuses
    Route::get('bonuses', [PatientBonusController::class, 'index']);
    Route::get('bonuses/{id}', [PatientBonusController::class, 'show']);

    // Payments
    Route::get('payments', [PatientPaymentController::class, 'index']);
    Route::get('payments/pending', [PatientPaymentController::class, 'pending']);

    // Consents
    Route::get('consents', [PatientConsentController::class, 'index']);
    Route::get('consents/{id}', [PatientConsentController::class, 'show']);
    Route::post('consents/{id}/sign', [PatientConsentController::class, 'sign']);

    // Documents
    Route::get('documents', [PatientDocumentController::class, 'index']);
    Route::get('documents/{id}', [PatientDocumentController::class, 'show']);
    Route::get('documents/{id}/download', [PatientDocumentController::class, 'download']);

    // Notifications
    Route::get('notifications', [PatientNotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [PatientNotificationController::class, 'markRead']);
});
```

> **Desviación 22.4:** las rutas usan `{id}` numérico + queries scoped en services/controllers
> (`firstOrFail()`), **no** route model binding ni middleware `can:`. El aislamiento (404 si no
> es del paciente/clínica) lo garantiza la query scoped.

---

## 15. Authorization (Policies)

Each policy enforces **both** patient identity and tenant boundary:

```php
// Example: PatientAppointmentPolicy
class PatientAppointmentPolicy
{
    public function view(Patient $patient, Appointment $appointment): bool
    {
        return $appointment->patient_id === $patient->id
            && $appointment->clinic_id === $patient->clinic_id;
    }

    public function cancel(Patient $patient, Appointment $appointment): bool
    {
        if (!$this->view($patient, $appointment)) return false;
        return $appointment->start_time->diffInHours(now()) >= 24;
    }

    public function update(Patient $patient, Appointment $appointment): bool
    {
        return $this->view($patient, $appointment);
    }
}
```

### Policy Summary

| Policy | Methods | Rules |
|--------|---------|-------|
| `PatientAppointmentPolicy` | `view`, `cancel`, `update` | `patient_id` + `clinic_id` match; cancel requires 24h+ |
| `PatientBonusPolicy` | `view` | `patient_id` + `clinic_id` match |
| `PatientPaymentPolicy` | `view` | `patient_id` + `clinic_id` match |
| `PatientConsentPolicy` | `view`, `sign` | `patient_id` + `clinic_id` match; sign only if `status = 'sent'` |
| `PatientDocumentPolicy` | `view` | `patient_id` + `clinic_id` match |
| `PatientNotificationPolicy` | `update` (markRead) | `patient_id` + `clinic_id` match |

### Policy Registration

> **Desviación 22.5:** las 6 policies **existen pero NO se registran** en
> `AuthServiceProvider`. El aislamiento dual lo garantizan las queries scoped de los
> services (ver §14). Motivo: las policies de backoffice comparten modelo (`Appointment`,
> `Bonus`, `Payment`, …) y registrarlas entraría en conflicto con la autorización de
> usuarios. Los 12 tests de autorización (IDOR/cross-tenant) cubren el mismo nivel de
> control sin policies registradas.

---

## 16. Frontend Structure

### 16.1 Directory Layout (real)

```
resources/js/
├── router/
│   ├── index.js                # Router unificado: backofficeRoutes + patientRoutes ✔ Desviación 22.6
│   └── patient.js              # patientRoutes bajo /patient/ (sin createRouter propio)
├── layouts/
│   └── patient/
│       └── PatientLayout.vue   # Mobile-first layout con navegación
├── patient/
│   ├── composables/
│   │   └── usePatientAuth.js   # Estado de auth, login/logout, token, branding (`clinicBranding`, `fetchClinicBranding(slug)`)
│   └── services/
│       └── patientApi.js       # Axios instance con token de paciente
└── views/
    └── patient/
        ├── Login.vue, ForgotPassword.vue, ResetPassword.vue
        ├── Dashboard.vue, Profile.vue
        ├── Appointments.vue, AppointmentDetail.vue, AppointmentRequest.vue
        ├── Bonuses.vue, BonusDetail.vue
        ├── Payments.vue
        ├── Consents.vue, ConsentDetail.vue
        ├── Documents.vue
        └── Notifications.vue
```

> No existe `stores/` (Pinia) ni carpeta `components/` dedicada: las vistas son
> autocontenidas y usan componentes globales de la app.

### 16.2 Routing

```javascript
// resources/js/patient/router/patient.js
const patientRoutes = [
  { path: '/patient/login', component: Login, meta: { guest: true } },
  { path: '/patient/forgot-password', component: ForgotPassword, meta: { guest: true } },
  { path: '/patient/reset-password', component: ResetPassword, meta: { guest: true } },
  {
    path: '/patient',
    component: PatientLayout,
    meta: { requiresPatientAuth: true },
    children: [
      { path: '', redirect: '/patient/dashboard' },
      { path: 'dashboard', component: Dashboard },
      { path: 'profile', component: Profile },
      { path: 'appointments', component: Appointments },
      { path: 'appointments/request', component: AppointmentRequest },
      { path: 'appointments/:id', component: AppointmentDetail },
      { path: 'bonuses', component: Bonuses },
      { path: 'bonuses/:id', component: BonusDetail },
      { path: 'payments', component: Payments },
      { path: 'consents', component: Consents },
      { path: 'consents/:id', component: ConsentDetail },
      { path: 'documents', component: Documents },
      { path: 'notifications', component: Notifications },
    ],
  },
];
```

### 16.3 Design Principles

- **Mobile-first**: all pages designed for 320px+ screens
- **Bottom navigation**: Dashboard, Appointments, Bonuses, Profile
- **Large touch targets**: minimum 44px tap areas
- **Simple language**: avoid medical jargon
- **Offline-aware**: show cached data when available
- **Pull-to-refresh**: on list pages

---

## 17. Security

### 17.1 Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /api/patient/auth/login` | 5 attempts | 1 minute (clave: `email\|ip`) |
| `POST /api/patient/auth/forgot-password` | 3 attempts | 1 minute (clave: `email`) |
| All other endpoints | Default throttle `api` | — |

> **Desviación 22.7:** no hay rate limit específico en `consents/{id}/sign` (el borrador
> proponía 5/min); los limiters se definen en `modules/PatientPortal/Routes/api.php`.

### 17.2 Input Validation

- All inputs validated via Laravel `FormRequest` classes
- SQL injection: prevented by Eloquent parameterized queries
- XSS: Vue's template escaping + `v-text` for untrusted content
- CSRF: SPA uses Sanctum tokens (stateful), no CSRF token needed for API

### 17.3 Data Protection

- Passwords: bcrypt hashed, never returned in API responses
- Tokens: stored in `personal_access_tokens`, never exposed to frontend localStorage in plaintext
- Documents: private storage, no public URLs
- Consent signatures: stored as SVG + hash for integrity

### 17.4 Anti-IDOR

- Every policy check validates `patient_id` + `clinic_id`
- Route model binding uses scoped queries (filtered by clinic)
- No raw resource IDs exposed to frontend

---

## 18. Audit

### 18.1 Events Logged

| Event | Trigger | Data |
|-------|---------|------|
| `PatientLoggedIn` | Successful login | `patient_id`, `ip`, `user_agent` |
| `PatientAppointmentRequested` | New appointment request | `patient_id`, `appointment_id`, `preferred_date` |
| `PatientAppointmentCancelled` | Patient cancels | `patient_id`, `appointment_id`, `reason` |
| `PatientConsentSigned` | Consent signed | `patient_id`, `consent_id`, `ip` |
| `PatientProfileUpdated` | Profile change | `patient_id`, `changed_fields` |
| `PatientDocumentDownloaded` | Document download | `patient_id`, `document_id`, `ip` |

### 18.2 Storage

Audit events stored in the `activity_log` table (Laravel Activitylog package, if present) or a dedicated `patient_audit_logs` table:

```sql
CREATE TABLE patient_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    event VARCHAR(255) NOT NULL,
    description TEXT,
    properties JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NULL,
    INDEX idx_patient_clinic (clinic_id, patient_id),
    INDEX idx_event (event),
    INDEX idx_created (created_at)
);
```

---

## 19. Tests

### 19.1 Test Categories (implementado — 85 tests, 291 aserciones)

| Category | Priority | Real | Files |
|----------|----------|------|-------|
| Authentication | Critical | 13 | `PatientAuthTest.php` |
| Authorization (IDOR/cross-tenant) | Critical | 12 | `PatientAuthorizationTest.php` |
| Appointments | High | 10 | `PatientAppointmentTest.php` |
| Bonuses | High | 6 | `PatientBonusTest.php` |
| Payments | Medium | 4 | `PatientPaymentTest.php` |
| Consents | High | 6 | `PatientConsentTest.php` |
| Documents | Medium | 4 | `PatientDocumentTest.php` |
| Notifications | Medium | 6 (incluye 2 de listener) | `PatientNotificationTest.php` |
| Profile | Medium | 4 | `PatientProfileTest.php` |
| Security (rate limit, tokens) | Critical | 6 | `PatientSecurityTest.php` |
| Admin (portal-access) | Critical | 8 | `PatientPortalAdminTest.php` |
| Branding público (endpoint slug) | Medium | 5 | `PublicClinicBrandingTest.php` |
| **Total** | | **85** | `tests/Feature/PatientPortal/` |

> No hay factories: los tests crean modelos directamente (`Clinic::create`, `Patient::create`, …)
> desde el `PatientPortalTestCase` base (2 clínicas, 3 pacientes, login real en `setUp`).
> Detalle completo en `docs/qa/patient-portal.md`.

### 19.2 Critical Authorization Tests

- Patient A cannot access Patient B's appointment (same clinic) → `403`
- Patient A cannot access Patient C's appointment (different clinic) → `403`
- Patient A cannot cancel Patient B's appointment → `403`
- Patient A cannot view Patient B's payments → `403`
- Patient A cannot sign Patient B's consent → `403`
- Patient A cannot download Patient B's documents → `403`
- Patient token cannot access backoffice endpoints → `401`
- Backoffice token cannot access patient endpoints → `401`

---

## 20. Definition of Done

- [x] Patient migration adds `password`, `email_verified_at`, `last_login_at`, `status` columns
- [x] `Patient` model has `HasApiTokens` trait (+ `CanResetPassword`, ver §22.9)
- [x] `config/auth.php` has `patient` guard and `patients` provider (+ broker)
- [x] `PatientAuth` middleware autentica vía token Sanctum; grupo protegido incluye `auth:patient`
- [x] `PatientClinic` middleware fija contexto de clínica
- [x] Los 9 controllers implementados con validación
- [x] Las 6 policies definidas con checks duales (existen, no registradas — ver §22.5)
- [x] Todos los endpoints testeado (happy path + autorización + edge cases): **70 tests**
- [x] Frontend: Login, ForgotPassword, ResetPassword funcionales
- [x] Frontend: Dashboard con datos agregados correctos
- [x] Frontend: vistas CRUD funcionales (Appointments, Bonuses, Payments, Consents, Documents, Notifications, Profile)
- [x] Vista responsive mobile-first (layout con navegación)
- [x] Audit logging en todos los eventos críticos (`patient_audit_logs`)
- [x] Rate limiting en endpoints de auth
- [x] Sin vulnerabilidades IDOR (12 tests de autorización verdes)
- [x] Documentación actualizada (spec + backend guide + QA + cliente)
- [~] Code review (pendiente de revision humana)

---

## 21. Implementation Phases

### Phase 1: Auth + Migrations
- [ ] Database migration for new patient fields
- [ ] `config/auth.php` patient guard + provider
- [ ] `Patient` model: `HasApiTokens` trait, `$hidden`, `$casts`
- [ ] `PatientAuth` + `PatientClinic` middleware
- [ ] `PatientAuthService` (login, logout, forgotPassword, resetPassword)
- [ ] `PatientAuthController` with all auth endpoints
- [ ] Rate limiting configuration
- [ ] Unit tests for auth flow

### Phase 2: Dashboard + Profile
- [ ] `PatientDashboardService` with aggregation logic
- [ ] `PatientDashboardController`
- [ ] `PatientProfileService` (get, update)
- [ ] `PatientProfileController`
- [ ] `ProfileUpdateDTO` with validation
- [ ] Frontend: Login, ForgotPassword, ResetPassword pages
- [ ] Frontend: PatientLayout with navigation
- [ ] Frontend: Dashboard page
- [ ] Frontend: Profile page

### Phase 3: Appointments
- [ ] `PatientAppointmentService` (upcoming, history, detail, request, cancel, reschedule)
- [ ] `PatientAppointmentController`
- [ ] `PatientAppointmentPolicy`
- [ ] `AppointmentRequestDTO`
- [ ] Upcoming/History queries with filters
- [ ] Cancellation 24h policy enforcement
- [ ] Frontend: Appointments list (upcoming + history tabs)
- [ ] Frontend: AppointmentDetail page
- [ ] Frontend: AppointmentRequest form
- [ ] Integration tests for appointment flows

### Phase 4: Bonuses
- [ ] `PatientBonusService` (list, detail)
- [ ] `PatientBonusController`
- [ ] `PatientBonusPolicy`
- [ ] Status computation (reuse `Bonus::status` accessor)
- [ ] Session lines display
- [ ] Frontend: Bonuses list
- [ ] Frontend: BonusDetail with session lines
- [ ] Integration tests

### Phase 5: Payments (Manual)
- [ ] `PatientPaymentService` (history, pending)
- [ ] `PatientPaymentController`
- [ ] `PatientPaymentPolicy`
- [ ] Frontend: Payments page (history + pending)
- [ ] Integration tests

### Phase 6: Consents + Documents
- [ ] `PatientConsentService` (list, detail, sign)
- [ ] `PatientConsentController`
- [ ] `PatientConsentPolicy`
- [ ] Reuse `ConsentSignatureService` for signing
- [ ] `PatientDocumentService` (list, detail, download)
- [ ] `PatientDocumentController`
- [ ] `PatientDocumentPolicy`
- [ ] Frontend: Consents list + detail + sign flow
- [ ] Frontend: SignaturePad component
- [ ] Frontend: Documents page
- [ ] Integration tests

### Phase 7: Notifications
- [ ] `patient_portal_notifications` migration
- [ ] `PatientPortalNotification` model
- [ ] `PatientNotificationService` (list, markRead)
- [ ] `PatientNotificationController`
- [ ] `PatientNotificationPolicy`
- [ ] Notification creation hooks (from appointment/consent/payment events)
- [ ] Frontend: Notifications page
- [ ] Frontend: Unread count badge in navigation
- [ ] Integration tests

### Phase 8: Tests + Security Audit
- [x] Full test suite (auth, authorization, endpoints, edge cases) — 70 tests verdes
- [x] Security audit (IDOR, rate limiting, input validation)
- [x] Performance review (query optimization, N+1 checks)
- [x] Mobile responsiveness audit
- [ ] Accessibility review (pendiente)
- [x] Documentation finalization

---

## 22. Desviaciones decididas y bugs corregidos (2026-08-30)

Resumen de diferencias entre este spec (borrador) y la implementación final. Detalle en `docs/backend/patient-portal.md` y `docs/qa/patient-portal.md`.

| # | Área | Desviación / Bug | Estado |
|---|---|---|---|
| 22.1 | Estructura | Sin `PatientDashboardDTO`, `Queries/`, `Persistence/`, `PatientNotFoundException`; añadido `Listeners/`; validación inline en controllers (sin FormRequests) | Decidido |
| 22.2 | Cancelación | Estado `canceled` (una L), el canónico de la app; el portal escribe `canceled` | Decidido |
| 22.3 | Consentimiento | `sign()` **no** setea `signed_by` (FK a `users`); identidad por `patient_id` + audit + ip/user_agent. Bug real: la firma desde el portal daba `FOREIGN KEY constraint failed` | Bug corregido (D) |
| 22.4 | Rutas | `{id}` numérico + queries scoped (`firstOrFail()` → 404), sin route model binding ni `can:` | Decidido |
| 22.5 | Policies | Existen pero **no se registran** (conflicto con policies de backoffice por modelo) | Decidido |
| 22.6 | Router frontend | Router unificado en `resources/js/router/index.js` (`backofficeRoutes` + `patientRoutes`). Bug real: el portal daba `ReferenceError` (solo se montaba backoffice) | Bug corregido (T1) |
| 22.7 | Rate limiting | Sin limit en `consents/{id}/sign`; limiter login por `email\|ip` | Decidido |
| 22.8 | Auth | Grupo protegido exige **`auth:patient`** antes de `patient.auth`. Bug real: el token Bearer nunca se resolvía → 401 en todas las rutas protegidas | Bug corregido (A) |
| 22.9 | Password reset | `Patient` implementa `CanResetPasswordContract` + override `sendPasswordResetNotification` → `ResetPasswordNotificationEs`. Bug real: `forgot/reset` lanzaban 500/TypeError | Bug corregido (B) |
| 22.10 | Bonus detail | `PatientBonusService::show()` carga `sessionLines.appointmentType` (relación real), no `bonusType`. Bug real: 500 con bonos multi-tipo | Bug corregido (C) |
| 22.11 | Acceso opt-in | El borrador dejaba la activación del acceso en manos de Irison. Implementado **autogestionable por la clínica**: `PUT /api/patients/{patient}/portal-access` (`PatientsServices::setPortalAccess`); `status = inactive` revoca todos los tokens Sanctum del paciente (cierre de sesiones). Campos `portal_status`, `has_portal_access`, `last_login_at` expuestos en `mapPatient()`/`show()` | Decidido |
| 22.12 | Reset URL | El email de reset del paciente apuntaba a la ruta de **staff** `/reset-password` (callback global `ResetPassword::createUrlUsing` en `AppServiceProvider`) y fallaba con 422. Nuevo `PatientResetPasswordNotification` con URL `/patient/reset-password?token=&email=&clinic={slug}`; el staff conserva su flujo | Bug corregido (E) |
| 22.13 | Branding de clínica | Todo lo que ve el paciente (emails + portal SPA) muestra **nombre/logo de la clínica, nunca Irison**. Endpoint público `GET /api/patient/public/branding/{clinic.slug}` (decisión: slug en la URL del portal, vía `?clinic=`); `email-clinic-header` sin marca de agua Irison; From name = clínica en emails a paciente. Staff/backoffice mantiene Irison. Detalle en `docs/backend/patient-portal.md` §12 | Decidido |
| 22.14 | Configuración del slug del portal | Tab "Portal del Paciente" en Servicios con **slug propio** (independiente de `booking_pages.slug`). Endpoints staff `GET/PUT /api/patient-portal/settings` + `GET /api/patient-portal/slug-check` (`PatientPortalSettingsController`, autorización owner/admin/manager). Nuevo tab `PatientPortalSettings.vue` integrado en `company-services/Index.vue`. **Auto-generación de `clinics.slug` al registrarse** (`RegisterController`, `Str::slug` + sufijo si colisiona); clínicas existentes sin slug → sugerencia `Str::slug(name)` como valor inicial editable. Detalle: `docs/backend/patient-portal.md` §13 | Decidido |
