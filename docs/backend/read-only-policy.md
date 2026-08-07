# Política de Solo Lectura Post-Trial (Read-Only)

> Aplica cuando una clínica entra en **modo solo lectura** tras el fin del trial (o tras el fin del periodo pagado de una cancelación). Define qué puede y qué no puede hacer el tenant hasta que reactive el pago.

## Cuándo se activa

El modo solo lectura se activa en **cualquiera** de estas situaciones (ver `Clinic::isReadOnlyNoTransactionsMode()`):

1. **Trial vencido dentro de la semana de gracia** — `subscription_status ∈ {trial, trial_warning}` y `trial_ends_at` en el pasado (dentro de `billing.trial_grace_days`, por defecto 7 días).
2. **Residuo de estado `trial_read_only`** — `clinics.status = 'trial_read_only'` (histórico de la normalización; `MeController` lo expone como `status='trial_read_only'`).
3. **Cancelación sin periodo pagado vigente** — `subscription_status = canceled/cancelled` fuera del periodo pagado (ventana de gracia de cancelación).

El frontend replica esta lógica en `MainLayout.vue` (`isReadOnlyNoTransactions`) y en la guarda axios (`api.js`).

## Qué se permite (y solo esto)

| Acción | Endpoint / UI |
|---|---|
| **Ver datos existentes** (lectura) | Cualquier método seguro (GET) de los módulos protegidos por `check.subscription` |
| **Activar la cuenta de pago** | `POST /api/billing/checkout`, `POST /api/billing/confirm`, `POST /api/stripe/checkout`, `POST /api/subscribe/fake` (lista blanca del middleware) |
| **Descargar el backup XLSX** | `GET /api/settings/subscription/backup` (es GET, permitido) |

## Qué se bloquea

- **Toda escritura de datos del tenant**: pacientes, citas, pagos, documentos/facturas, productos, bonos, consentimientos, equipo, actividad, finanzas, configuración de reserva online, etc. → `403 CLINIC_READ_ONLY_NO_TRANSACTIONS`.
- **Solicitud de upgrade** (`POST /settings/subscription/request`).
- **Reserva online pública**: `POST /api/booking` y `POST /api/booking/cancel/{token}` devuelven `422` cuando la clínica está en solo lectura (la página pública `GET /api/booking/{slug}`, disponibilidad y slots siguen visibles).
- Después de la semana de gracia (estado `blocked`/`churned`) se bloquea **todo** el API, incluidos los GET; el backup **solo** está disponible durante la ventana de solo lectura.

## Dónde se aplica (puntos de enforcement)

1. **Backend — middleware `check.subscription`** (`app/Http/Middleware/CheckSubscriptionAccess.php`):
   - Paso 3: `isSubscribed() || isTrialActive() || isInCancellationPaidWindow()` → acceso completo.
   - Paso 5: `isReadOnlyNoTransactionsMode()` → permite métodos seguros + `canStartPaidPlanWhileReadOnly()` (lista blanca de checkout); bloquea el resto con 403.
   - Paso 7: resto de casos (blocked/churned) → 403 `SUBSCRIPTION_REQUIRED`.
2. **Backend — cobertura de rutas**: todos los módulos protegen sus rutas con `check.subscription` (`Subscriptions`, `Bonus`, `Notifications`, `Activity`, `Finance`, `routes/api.php`). **El módulo Booking también**: `modules/Booking/Routes/api.php` incluye `check.subscription` en el grupo admin (`/booking/settings|services|professionals|schedules|exceptions`), de modo que sus escrituras se bloquean en solo lectura.
3. **Backend — reserva pública**: `PublicBookingService::ensureClinicCanBeBooked()` lanza `DomainException` si la clínica está en solo lectura (`createAppointment` y `cancelByToken`).
4. **Frontend — CSS `.readonly-mode`** (`MainLayout.vue:591-612`): oculta `.btn-primary`, `button[type=submit]`, `.save-button`, `.action-btn`, enlaces `/edit`, `/create`, tarjetas de acción rápida, etc. La clase `allow-readonly-action` re-muestra elementos concretos que deben seguir operativos (botones de backup y activación).
5. **Frontend — guarda axios** (`resources/js/services/api.js`): si el estado es solo lectura y el método no es GET (y no está en la lista blanca), rechaza la petición en el cliente antes de enviarla, mostrando el aviso "Modo solo lectura". Lista blanca: `/billing/checkout`, `/billing/confirm`, `/stripe/checkout`, `/subscribe/fake`.

## Botones que siguen visibles en solo lectura

- **"Activar cuenta de pago"** — `Configuration.vue` (botón con `allow-readonly-action`) y banner de `MainLayout.vue`.
- **"Generar backup (.xlsx)"** — `resources/js/views/settings/Subscription.vue` (botón con `allow-readonly-action`; la sección se muestra cuando `canBackup` = `trial_read_only` o cancelada en solo lectura).
- **"Solicitar upgrade"** — se **oculta** (no está en la política).

## Tests de referencia

- `tests/Feature/Booking/BookingReadOnlyPolicyTest.php` — cobertura de la política en el módulo Booking (admin bloqueado, público bloqueado, backup permitido, checkout permitido).
- `tests/Feature/Billing/BillingLifecycleTest.php` — transiciones de estado del ciclo de suscripción.
- `tests/Feature/Backoffice/ClinicAlertsReconcileTest.php` — badge de estado en backoffice.
