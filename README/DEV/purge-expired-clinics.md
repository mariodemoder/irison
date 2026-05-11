# Purga de datos de clínicas expiradas

## Contexto

Cuando una clínica deja de estar activa (trial expirado o suscripción cancelada), se le concede un **periodo de gracia de 7 días** en modo solo lectura (no puede transaccionar). Pasado ese periodo, sus datos operativos se eliminan en cascade.

Los datos que **se conservan** son:
- `clinics` — el registro de la clínica (marcado como `deleted_at`, soft delete)
- `subscriptions` — historial de suscripciones (Cashier/Stripe)
- `billing_payments` — historial de pagos

---

## Arquitectura

### Servicio: `App\Services\ClinicDataPurgeService`

Borra en orden inverso de dependencia de FK para evitar violaciones de integridad:

```
document_items         ← hijos de documents
patient_images         ← hijos de patients
credit_usages
bonus_usages
reminders
clinical_records
payments
documents
appointments
bonuses
patients
appointment_types
bonus_types
products
counters_clinics
personal_access_tokens ← tokens de los usuarios
users
```

Todo se ejecuta dentro de un `DB::transaction` — si algo falla, no queda borrado a medias.

Al finalizar la transacción, la clínica recibe **soft delete** (`deleted_at` se rellena). El registro en `clinics` sigue existiendo en la BD para auditoría pero queda invisible para el modelo.

### Comando: `App\Console\Commands\PurgeExpiredClinics`

Detecta clínicas elegibles y llama al servicio por cada una. Una clínica es elegible si cumple **las tres** condiciones:

1. `subscription_status` es `trial`, `canceled` o `cancelled`
2. `isTrialActive()` → `false`
3. `isInReadOnlyNoTransactionsWindow()` → `false` (el grace period de 7 días ya expiró)

---

## Ejecución

### Manual (inmediata)
```bash
php artisan clinics:purge-expired
```

### Dry-run (ver qué se borraría sin ejecutar)
```bash
php artisan clinics:purge-expired --dry-run
```

### Scheduler automático
El comando se ejecuta cada día a las **03:00** vía el scheduler de Laravel (`bootstrap/app.php`).

Para probarlo manualmente a través del scheduler:
```bash
php artisan schedule:test
# selecciona PurgeExpiredClinics en el menú

# O ver todas las tareas programadas:
php artisan schedule:list
```

---

## Lógica de grace period

Definida en `App\Models\Clinic::isInReadOnlyNoTransactionsWindow()`:

| Estado               | Condición grace period activo                           |
|----------------------|---------------------------------------------------------|
| `trial`              | `now` <= `trial_ends_at + 7 días`                       |
| `canceled/cancelled` | `now` <= `subscription.current_period_end`              |

El middleware `CheckSubscriptionAccess` permite GET durante el grace period y bloquea mutaciones con `403 CLINIC_READ_ONLY_NO_TRANSACTIONS`.

---

## Tests relacionados

- `tests/Feature/ClinicScopeTest.php` — scoping de clinic_id
- `tests/Feature/Authorization/PolicyAuthorizationTest.php` — autorización entre clínicas
````

