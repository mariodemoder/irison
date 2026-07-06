# Hard-Delete funcional de clínica

## Qué hace

Elimina todos los datos funcionales de una clínica **preservando**:
- El registro `clinics` (saneado: operacionales a null, name visible)
- Facturación Stripe: `subscriptions`, `subscription_requests`, `billing_payments`, `stripe_subscriptions` (Cashier)
- IDs de Stripe en la clínica

Elimina **~31 tablas**: users, patients, appointments, clinical_records, documents, document_items, bonuses, bonus_usages, bonus_types, payments, credit_usages, products, reminders, counters, appointment_types, booking_pages/services/professionals, schedules, consent_templates/categories, patient_consents, consent_logs, patient_images, professions, trial_journey_events, backoffice_clinic_activities, activity_logs, user_schedules, user_schedule_exceptions.

## Backoffice manual (super_admin)

**Ruta:** `POST /clinics/{clinic}/hard-delete`

**Botón** en `show.blade.php` sección `#desde-aqui`, solo visible para `super_admin`, oculto si `functional_data_deleted_at IS NOT NULL`.

**Confirmación:** `confirm()` nativo, con mensaje más severo si la clínica tiene plan activo o trial vigente (banner rojo adicional antes del botón).

**Controller:** `ClinicController::hardDelete()` → `ClinicManagementService::hardDeleteFunctionalData()`

## Automatizado (cron)

**Comando:** `clinics:purge-expired` — corre diario a las 03:00.

**Elegibilidad:**
```php
Clinic::whereNull('functional_data_deleted_at')
    ->whereIn('subscription_status', ['trial', 'canceled', 'cancelled'])
    ->get()
    ->filter(fn ($c) =>
        !$c->isSubscribed() &&
        !$c->isTrialActive() &&
        !$c->isInReadOnlyNoTransactionsWindow()
    );
```
Es decir: clínicas que ya pasaron `trial_ends_at + grace_days` (7) o `current_period_end + read_only_days` (7).

**Dry-run:** `php artisan clinics:purge-expired --dry-run` — lista candidatos sin borrar.

## Servicio compartido

`ClinicManagementService::hardDeleteFunctionalData(Clinic $clinic, ?AdminUser $admin = null)`

- `$admin` opcional: si se pasa, registra `ActivityLogger` con `hard_delete_functional_data`.
- Todo dentro de `DB::transaction()`.
- Orden: desarma FK → tablas hoja → users → patients (cascada DB) → direct restantes → activity_logs → saneo clinic + marca `functional_data_deleted_at`.

## Tests

`tests/Feature/Console/PurgeExpiredClinicsTest.php` — 7 tests:
- Dry-run lista candidatos
- Purga trial expirado
- Purga cancelado expirado
- Salta clínica activa
- Salta trial activo
- Salta ya limpiada
- Preserva billing data
