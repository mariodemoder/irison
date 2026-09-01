# Patient Portal — QA & Test Plan

| Field | Value |
|-------|-------|
| **Module** | `modules/PatientPortal/` |
| **Framework** | Laravel 12 (PHPUnit) + Vue 3 |
| **Test Location** | `tests/Feature/PatientPortal/` |
| **Estado** | **Ejecutado — todos verdes (70 tests / 246 aserciones), 2026-08-30** |

> ⚠️ **Aviso de fidelidad:** los códigos de ejemplo de este documento ilustran la
> intención de cada test; la fuente de verdad es `tests/Feature/PatientPortal/`.
> Diferencias clave con el borrador: **no hay factories** (se crean modelos con
> `::create`), la autenticación se hace con **login real** (`POST /api/patient/auth/login`)
> y cada request lleva el header `Authorization: Bearer <token>`, y el estado de
> cancelación es `canceled` (una L).

---

## 1. Test Categories

| Category | Priority | Real Count | Files |
|----------|----------|------------|-------|
| Authentication | Critical | 11 | `PatientAuthTest.php` |
| Authorization (IDOR / Cross-tenant) | Critical | 12 | `PatientAuthorizationTest.php` |
| Appointments | High | 10 | `PatientAppointmentTest.php` |
| Bonuses | High | 6 | `PatientBonusTest.php` |
| Payments | Medium | 4 | `PatientPaymentTest.php` |
| Consents | High | 6 | `PatientConsentTest.php` |
| Documents | Medium | 4 | `PatientDocumentTest.php` |
| Notifications | Medium | 6 (incluye 2 de listener) | `PatientNotificationTest.php` |
| Profile | Medium | 4 | `PatientProfileTest.php` |
| Security | Critical | 6 | `PatientSecurityTest.php` |
| **Total** | | **70** | `tests/Feature/PatientPortal/` |

---

## 2. Test Helpers

### 2.1 Base TestCase (real)

```php
// tests/Feature/PatientPortal/PatientPortalTestCase.php
abstract class PatientPortalTestCase extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;        // clínica principal
    protected Clinic $otherClinic;   // clínica ajena (cross-tenant)
    protected Patient $patient;      // paciente autenticado (email patient@portal.test)
    protected Patient $otherPatient; // otro paciente de la MISMA clínica (IDOR)
    protected Patient $foreignPatient;// paciente de OTRA clínica (cross-tenant)
    protected string $token;         // token del login real

    protected function setUp(): void
    {
        parent::setUp();
        // 2 clínicas (plan pro, subscription active)
        // 3 pacientes creados con ::create (NO factories)
        // token = loginAsPatient($this->patient) → POST /api/patient/auth/login
    }

    protected function patientHeaders(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token), 'Accept' => 'application/json'];
    }
}
```

Helpers de fixtures (sin factories): `makePatient`, `makeAppointment`, `makeBonus`,
`makePayment`, `makeNotification`, `makeConsentTemplate` (template published con
`version` obligatorio), `makeConsentFor` (incluye `template_version`), `makeDocumentFor`
(`typeinvoice = 'appointment'`, obligatorio NOT NULL), `makeAppointmentType`
(necesario para `bonus_session_lines.appointment_type_id`).

---

## 3. Authentication Tests

**File:** `tests/Feature/PatientPortal/PatientAuthTest.php` — **11 tests**

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_patient_can_login_with_valid_credentials` | POST `/api/patient/auth/login` with correct email + password | 200, token returned |
| 2 | `test_patient_cannot_login_with_wrong_password` | POST `/api/patient/auth/login` with wrong password | 401 |
| 3 | `test_patient_cannot_login_with_wrong_email` | POST `/api/patient/auth/login` with non-existent email | 401 |
| 4 | `test_inactive_patient_cannot_login` | Patient with `status = 'inactive'` attempts login | 403 |
| 5 | `test_patient_can_logout` | POST `/api/patient/auth/logout` with valid token | 200, token revoked |
| 6 | `test_patient_can_get_me` | GET `/api/patient/auth/me` with valid token | 200, patient profile |
| 7 | `test_unauthenticated_patient_cannot_access_protected_routes` | GET `/api/patient/dashboard` without token | 401 |
| 8 | `test_password_reset_flow_works` | Forgot + email + reset + login with new password | 200 at each step |
| 9 | `test_login_updates_last_login_at` | Último login se persiste (el setUp ya hace login) | `last_login_at` not null |
| 10 | `test_login_revokes_previous_tokens` | Un nuevo login revoca los tokens previos del paciente | 1 token tras relogin |
| 11 | `test_forgot_password_returns_neutral_response` | Email inexistente → respuesta neutral (no enumera) | 200, mismo mensaje |

### Test Details

```php
public function test_patient_can_login_with_valid_credentials(): void
{
    $response = $this->postJson('/api/patient/auth/login', [
        'email' => 'patient@portal.test',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'patient' => ['id', 'first_name', 'last_name', 'email', 'clinic_id'],
        ]);
}

public function test_inactive_patient_cannot_login(): void
{
    $this->patient->update(['status' => 'inactive']);

    $response = $this->postJson('/api/patient/auth/login', [
        'email' => 'patient@portal.test',
        'password' => 'password123',
    ]);

    $response->assertForbidden()
        ->assertJson(['message' => 'Su cuenta no está activa. Contacte con la clínica.']);
}

public function test_patient_can_logout(): void
{
    $response = $this->postJson('/api/patient/auth/logout', [], $this->patientHeaders());

    $response->assertOk();
    $this->assertDatabaseCount('personal_access_tokens', 0);
}
```

> La autenticación de los tests usa el **endpoint de login real** (flujo Sanctum completo),
> incluido en el `setUp` del TestCase. El logout revoca el token actual → la cuenta queda a 0 tokens.

---

## 4. Authorization Tests (CRITICAL)

**File:** `tests/Feature/PatientPortal/PatientAuthorizationTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_patient_cannot_access_other_patient_appointment_same_clinic` | Patient A tries to view Patient B's appointment (same clinic) | 403 or 404 |
| 2 | `test_patient_cannot_access_other_patient_appointment_different_clinic` | Patient A tries to view Patient C's appointment (different clinic) | 403 or 404 |
| 3 | `test_patient_cannot_cancel_other_patient_appointment` | Patient A tries to cancel Patient B's appointment | 403 or 404 |
| 4 | `test_patient_cannot_view_other_patient_payments` | Patient A tries to view Patient B's payments | 403 or 404 |
| 5 | `test_patient_cannot_view_other_patient_bonuses` | Patient A tries to view Patient B's bonuses | 403 or 404 |
| 6 | `test_patient_cannot_sign_other_patient_consent` | Patient A tries to sign Patient B's consent | 403 or 404 |
| 7 | `test_patient_cannot_view_other_patient_documents` | Patient A tries to view Patient B's documents | 403 or 404 |
| 8 | `test_patient_cannot_read_other_patient_notifications` | Patient A tries to mark Patient B's notification as read | 403 or 404 |
| 9 | `test_patient_token_cannot_access_backoffice_endpoints` | Patient token used on `/api/appointments` (backoffice) | 401 |
| 10 | `test_backoffice_token_cannot_access_patient_endpoints` | User token used on `/api/patient/dashboard` | 401 |
| 11 | `test_cross_tenant_access_returns_404` | Patient from Clinic A queries resources in Clinic B | 404 |
| 12 | `test_suspended_patient_cannot_access_any_endpoint` | Suspended patient tries any protected endpoint | 403 |

### Test Details

```php
public function test_patient_cannot_access_other_patient_appointment_same_clinic(): void
{
    $appointment = Appointment::factory()->create([
        'clinic_id' => $this->clinic->id,
        'patient_id' => $this->otherPatient->id,
        'status' => 'scheduled',
        'start_time' => now()->addDays(5),
    ]);

    $response = $this->actingAs($this->patient, 'patient')
        ->getJson("/api/patient/appointments/{$appointment->id}");

    $response->assertForbidden();
}

public function test_patient_token_cannot_access_backoffice_endpoints(): void
{
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson('/api/appointments');

    $response->assertUnauthorized();
}
```

---

## 5. Appointment Tests

**File:** `tests/Feature/PatientPortal/PatientAppointmentTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_upcoming_appointments_returned` | GET `/api/patient/appointments/upcoming` | 200, filtered list |
| 2 | `test_history_returned_with_filters` | GET with `from`, `to`, `status` params | 200, filtered paginated |
| 3 | `test_appointment_detail_shows_correct_data` | GET `/api/patient/appointments/{id}` | 200, full data |
| 4 | `test_cancellation_works_24h_plus` | POST cancel on appointment >24h away | 200, status = `canceled` |
| 5 | `test_cancellation_rejected_within_24h` | POST cancel on appointment <24h away | 403 + mensaje 24h |
| 6 | `test_appointment_request_created` | POST `/api/patient/appointments/requests` | 201, status `scheduled`, `booking_source` = patient_portal |
| 7 | `test_reschedule_creates_request` | POST reschedule | 200, old `rescheduled` + new created |
| 8 | `test_cancellation_restores_bonus` | Cancel appointment with bonus applied | Bonus `remaining_sessions` restaurado |
| 9 | `test_history_pagination_works` | Large dataset paginated correctly | 200, paginated response |
| 10 | `test_upcoming_excludes_completed` | Completed appointments not in upcoming | 200, empty or filtered |

### Test Details

```php
public function test_cancellation_works_24h_plus(): void
{
    $appointment = $this->makeAppointment($this->patient, ['status' => 'scheduled']); // +3 días

    $response = $this->postJson(
        "/api/patient/appointments/{$appointment->id}/cancel",
        [],
        $this->patientHeaders()
    );

    $response->assertOk();
    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'canceled',
    ]);
}

public function test_cancellation_rejected_within_24h(): void
{
    $appointment = $this->makeAppointment($this->patient, [
        'start_time' => now()->addHours(12),
        'end_time' => now()->addHours(13),
    ]);

    $response = $this->postJson(
        "/api/patient/appointments/{$appointment->id}/cancel",
        [],
        $this->patientHeaders()
    );

    $response->assertForbidden()
        ->assertJson(['message' => 'No es posible cancelar con menos de 24h de antelación. Contacte con la clínica.']);
}
```

> ✔ La regla 24h la aplica `PatientAppointmentService::cancel()` comparando
> `$appointment->start_time->lessThan(now()->addHours(24))` (robusto ante Carbon 3
> con `diffInHours` con signo). El estado persistido es `canceled` (una L) — ver
> `docs/backend/patient-portal.md` §4.

---

## 6. Bonus Tests

**File:** `tests/Feature/PatientPortal/PatientBonusTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_bonuses_list_returned` | GET `/api/patient/bonuses` | 200, list |
| 2 | `test_bonus_detail_shows_correct_remaining` | GET `/api/patient/bonuses/{id}` | 200, remaining_sessions accurate |
| 3 | `test_expired_bonuses_shown` | Bonus past `expires_at` | status = 'expired' |
| 4 | `test_exhausted_bonuses_shown` | Bonus with `remaining_sessions = 0` | status = 'exhausted' |
| 5 | `test_last_session_bonus_shown` | Bonus with `remaining_sessions = 1` | status = 'last' |
| 6 | `test_session_lines_displayed` | Bonus with multi-type session lines | session_lines array present |

### Test Details

```php
public function test_bonuses_list_returned(): void
{
    $this->makeBonus($this->patient, ['remaining_sessions' => 5, 'expires_at' => now()->addMonth()]);

    $response = $this->getJson('/api/patient/bonuses', $this->patientHeaders());

    $response->assertOk()
        ->assertJsonCount(1, 'bonuses');
}

public function test_expired_bonuses_shown(): void
{
    $this->makeBonus($this->patient, ['remaining_sessions' => 3, 'expires_at' => now()->subDay()]);

    $response = $this->getJson('/api/patient/bonuses', $this->patientHeaders());

    $response->assertOk()
        ->assertJsonFragment(['status' => 'expired']);
}
```

> ✔ El detalle de bonus (`GET /api/patient/bonuses/{id}`) carga
> `sessionLines.appointmentType` (relación real de `BonusSessionLine`). El borrador/primera
> versión usaba `sessionLines.bonusType` (relación inexistente) → 500 con bonos multi-tipo;
> corregido (Bug C).

---

## 7. Payment Tests

**File:** `tests/Feature/PatientPortal/PatientPaymentTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_payment_history_returned` | GET `/api/patient/payments` | 200, paginated list |
| 2 | `test_pending_payments_returned` | GET `/api/patient/payments/pending` | 200, only pending |
| 3 | `test_no_payment_processing_endpoint_exists` | POST `/api/patient/payments` (should not exist) | 404 |
| 4 | `test_payments_show_correct_statuses` | Different payment statuses displayed correctly | 200, correct status labels |

---

## 8. Consent Tests

**File:** `tests/Feature/PatientPortal/PatientConsentTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_pending_consents_displayed` | GET `/api/patient/consents` | 200, consents with status = 'sent' |
| 2 | `test_patient_can_sign_consent` | POST `/api/patient/consents/{id}/sign` with SVG | 200, status = 'signed' |
| 3 | `test_signed_consent_cannot_be_modified` | POST sign on already-signed consent | 403 |
| 4 | `test_signature_belongs_to_correct_patient` | Signed consent has correct `patient_id` | Database check |
| 5 | `test_consent_detail_shows_template_content` | GET `/api/patient/consents/{id}` | 200, content_html present |
| 6 | `test_revoked_consent_not_signable` | POST sign on revoked consent | 403 |

### Test Details

```php
public function test_patient_can_sign_consent(): void
{
    $consent = $this->makeConsentFor($this->patient); // template + status 'sent'

    $response = $this->postJson(
        "/api/patient/consents/{$consent->id}/sign",
        ['signature_svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M10 80 Q 95 10, 180 80"/></svg>'],
        $this->patientHeaders()
    );

    $response->assertOk();
    $this->assertDatabaseHas('patient_consents', [
        'id' => $consent->id,
        'status' => 'signed',
        'patient_id' => $this->patient->id,
    ]);
}
```

> ✔ **Bug D corregido:** la primera versión de `PatientConsentService::sign()` pasaba
> `signed_by = patient->id`, pero `signed_by` es FK a `users` (staff) → toda firma desde el
> portal fallaba con `FOREIGN KEY constraint failed`. Se eliminó `signed_by` del meta
> (la identidad se captura por `patient_id` + audit log + ip/user_agent), igual que el
> flujo público `ConsentSignController`. El consentimiento firmado NO puede modificarse
> (`status !== 'sent'` → 400 `Este consentimiento ya ha sido firmado o revocado.`).

---

## 9. Document Tests

**File:** `tests/Feature/PatientPortal/PatientDocumentTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_shared_documents_listed` | GET `/api/patient/documents` | 200, list |
| 2 | `test_document_download_works` | GET `/api/patient/documents/{id}` | 200, file stream |
| 3 | `test_unauthorized_document_access_rejected` | Patient tries to download other's document | 403 |
| 4 | `test_documents_only_show_own_clinic` | No cross-clinic documents visible | 200, empty or filtered |

---

## 10. Notification Tests

**File:** `tests/Feature/PatientPortal/PatientNotificationTest.php` — **6 tests** (4 de endpoints + 2 de listener `CreatePatientPortalNotifications`)

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_notifications_listed` | GET `/api/patient/notifications` | 200, list |
| 2 | `test_mark_as_read_works` | POST `/api/patient/notifications/{id}/read` | 200, read_at set |
| 3 | `test_only_own_notifications_visible` | Cross-patient notifications not visible | 200, filtered |
| 4 | `test_unread_count_accurate` | Dashboard shows correct unread count | 200, accurate count |
| 5 | `test_appointment_updated_confirmed_creates_notification` | Evento `AppointmentUpdated` (status → confirmed) | notificación `appointment_confirmed` creada |
| 6 | `test_payment_created_pending_creates_notification` | Evento `PaymentCreated` (status pending) | notificación `payment_pending` creada |

---

## 11. Profile Tests

**File:** `tests/Feature/PatientPortal/PatientProfileTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_profile_data_returned` | GET `/api/patient/profile` | 200, patient data |
| 2 | `test_limited_fields_updatable` | PUT with allowed fields | 200, updated |
| 3 | `test_sensitive_fields_not_updatable` | PUT with `birth_date`, `email`, `nif` | 422 (ignored or rejected) |
| 4 | `test_profile_update_audited` | Profile change logged | Audit log created |

---

## 12. Security Tests

**File:** `tests/Feature/PatientPortal/PatientSecurityTest.php`

| # | Test | Description | Expected |
|---|------|-------------|----------|
| 1 | `test_rate_limiting_on_login` | 6 rapid login attempts | 429 on 6th |
| 2 | `test_sql_injection_attempts` | SQL injection in email field | 422 or safe rejection |
| 3 | `test_xss_attempts_in_profile` | XSS payload in `first_name` | Stored safely, escaped in output |
| 4 | `test_token_validation_works` | Invalid/forged token | 401 |
| 5 | `test_expired_token_rejected` | Token from deleted session | 401 |
| 6 | `test_patient_cannot_escalate_to_admin` | Patient tries to access admin routes | 401/403 |

---

## 13. Coverage Matrix

| Feature | Auth | AuthZ | Endpoint | Edge | Security | Total |
|---------|------|-------|----------|------|----------|-------|
| Login/Logout | 4 | - | 2 | - | 1 | 7 |
| Password Reset | 2 | - | 2 | - | - | 4 |
| Dashboard | 1 | 1 | 1 | - | - | 3 |
| Profile | - | - | 2 | 1 | 1 | 4 |
| Appointments | - | 4 | 4 | 2 | - | 10 |
| Bonuses | - | 2 | 2 | 2 | - | 6 |
| Payments | - | 1 | 2 | 1 | - | 4 |
| Consents | - | 2 | 2 | 2 | - | 6 |
| Documents | - | 2 | 2 | - | - | 4 |
| Notifications | - | 1 | 2 | 1 | - | 6 |
| Security | - | - | - | - | 6 | 6 |
| **Total** | **7** | **13** | **22** | **8** | **8** | **70** |

> Matriz conceptual (el desglose exacto por test está en la §1 y en los archivos de test).

---

## 14. Running Tests

```bash
# All patient portal tests — 70 tests / 246 aserciones, todos verdes
php artisan test --filter=PatientPortal
```

> Ejecución confirmada el 2026-08-30. El `phpunit.xml` usa SQLite `:memory:`,
> `QUEUE_CONNECTION=sync`, `APP_LOCALE=es`, `BCRYPT_ROUNDS=4`.
>
> **Nota de regresión:** los cambios del portal (`routes/api.php` del módulo, `app/Models/Patient.php`,
> listeners, `PaymentCreated`/`AppointmentReminderSent`) no introducen regresiones en el
> backoffice. La suite completa `Feature,Unit` presenta solo fallos **pre-existentes** (verificado
> con `git stash`): los tests Breeze por defecto (`Auth/*`, `Profile/*`) con errores
> `remember_token` (la tabla `users` no tiene esa columna) y 2 tests obsoletos
> (`RegistrationTest`, `RoleBasedAccessTest::test_professional_cannot_view_bonuses`).
> `php artisan test` además agota la memoria por defecto (128M) al ejecutar TODO en un
> proceso; usar `php -d memory_limit=1024M vendor/bin/phpunit --testsuite Feature,Unit`
> (o suite por suite) para corridas completas.

---

## 15. Manual QA Checklist

### Authentication
- [ ] Patient receives credentials email
- [ ] Patient can log in with email + password
- [ ] Patient can log out
- [ ] Patient can reset password via email
- [ ] Inactive patient sees appropriate error

### Dashboard
- [ ] Welcome message shows patient name
- [ ] Next appointment shows correct details
- [ ] Bonus summary is accurate
- [ ] Pending payments count is correct
- [ ] Pending consents list is correct
- [ ] Unread notification count is accurate

### Appointments
- [ ] Upcoming appointments listed correctly
- [ ] History shows past appointments
- [ ] Filters work (date, status, professional)
- [ ] Appointment request form works
- [ ] Cancellation works (>24h)
- [ ] Cancellation blocked (<24h) with correct message
- [ ] Reschedule creates new request

### Bonuses
- [ ] All bonuses listed (active, exhausted, expired)
- [ ] Bonus detail shows session lines
- [ ] Status badges display correctly

### Payments
- [ ] Payment history paginated correctly
- [ ] Pending payments filtered correctly
- [ ] No payment processing endpoint available

### Consents
- [ ] Pending consents highlighted
- [ ] Signature pad works on mobile
- [ ] Signed consent cannot be re-signed
- [ ] Consent detail shows template content

### Documents
- [ ] Documents list shows only own documents
- [ ] Download triggers file download
- [ ] Cross-clinic documents not visible

### Notifications
- [ ] Notifications listed newest first
- [ ] Mark as read updates UI
- [ ] Badge count updates after marking read

### Profile
- [ ] Profile data displays correctly
- [ ] Editable fields save successfully
- [ ] Non-editable fields (email, birth_date) rejected

### Mobile Responsiveness
- [ ] Login form usable on 320px screen
- [ ] Bottom navigation accessible
- [ ] Touch targets minimum 44px
- [ ] Tables scroll horizontally on mobile
- [ ] Forms stack vertically on small screens

---

## 16. Bugs reales de producción encontrados por la suite

| Bug | Síntoma | Causa raíz | Fix |
|-----|---------|------------|-----|
| **A** | Todas las rutas protegidas del portal daban **401** (logout, me, dashboard…) | El token Bearer nunca se resolvía: el grupo protegido usaba solo `patient.auth`/`patient.clinic`, sin resolución de guard Sanctum | Añadir **`auth:patient`** al grupo: `['auth:patient', 'patient.auth', 'patient.clinic']` |
| **B** | `forgot-password` / `reset-password` lanzaban **500/TypeError** | `Patient` no implementaba `CanResetPassword`; el broker esperaba el contrato | Añadir trait `CanResetPassword` + `CanResetPasswordContract` + override `sendPasswordResetNotification()` → `ResetPasswordNotificationEs` |
| **C** | `GET /api/patient/bonuses/{id}` → **500** con bonos multi-tipo | `show()` cargaba `sessionLines.bonusType`, relación inexistente en `BonusSessionLine` | Cargar `sessionLines.appointmentType` (relación real) |
| **D** | Firmar consentimiento desde el portal → **400** (`FOREIGN KEY constraint failed`) | `sign()` pasaba `signed_by = patient->id`, pero `signed_by` es FK a `users` (staff) | Eliminar `signed_by` del meta; identidad por `patient_id` + audit + ip/user_agent |

Estos 4 bugs eran inalcanzables para la suite previa (no existía catálogo de tests del
portal); la suite QA (70 tests) los expuso y verificó la corrección.
