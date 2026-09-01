---
name: bonus
description: Use when working on bonos: multi-type session lines (BonusSessionLine), BonusService, consumption flow, backward compatibility, bonus type templates, PatientBonuses.vue, session lines.
---

# Bonus Module Skill

## Arquitectura del Módulo (DDD)

```
modules/Bonus/
├── Config/bonus.php                     — Defaults del módulo
├── Contracts/
│   └── BonusConsumableInterface.php     — Interfaz para consumo de bonos
├── Database/Migrations/
│   ├── 2026_07_26_000100_create_bonus_session_lines_table.php
│   └── 2026_07_26_000200_add_appointment_type_id_to_bonus_usages_table.php
├── Http/Controllers/
│   ├── BonusController.php              — CRUD admin (index, show, update, destroy, expiring, issueInvoice)
│   ├── BonusTypeController.php          — CRUD de templates (tipos de bono)
│   └── PatientBonusController.php       — Assign/list bonuses per patient
├── Models/
│   ├── BonusType.php                    — Template de bono (usado por BonusTypeController)
│   └── BonusSessionLine.php             — Línea por tipo de sesión
├── Policies/
│   └── BonusPolicy.php                  — Autorización (extiende BasePolicy)
├── Providers/
│   └── BonusServiceProvider.php         — Registro del módulo + carga de rutas
├── Requests/Bonuses/
│   ├── StoreBonusRequest.php
│   └── UpdateBonusRequest.php
├── Routes/api.php                       — Todas las rutas del módulo
├── Services/
│   └── BonusService.php                 — Lógica de negocio multi-tipo (464 líneas)
└── tests/
    ├── Feature/
    │   └── BonusMultiTypeTest.php       — Tests HTTP (expiring, index)
    └── Unit/
        └── BonusServiceTest.php         — Tests unitarios (5 tests, 22 assertions)
```

## Modelos — Ubicación y Responsabilidades

### Modelos en `app/Models/` (entidades de dominio compartidas)

Estos modelos se mantienen en `app/Models/` porque son importados por 20+ archivos en toda la app. **No mover al módulo.**

| Modelo | Uso principal |
|--------|---------------|
| `App\Models\Bonus` | Modelo canónico del bono. Relación `sessionLines()` → `BonusSessionLine` |
| `App\Models\BonusType` | Template de bono. Usado por `CompanyServicesController` (Servicios page) |
| `App\Models\BonusUsage` | Tracking de uso. Usado por `Appointment`, `AppointmentService`, `BonusService` |

### Modelos en `modules/Bonus/Models/` (del módulo)

| Modelo | Responsabilidad |
|--------|-----------------|
| `BonusSessionLine` | Línea por tipo de sesión (cantidad y restante). Solo existe en el módulo |
| `BonusType` | Usado por `BonusTypeController` para CRUD de templates |

**IMPORTANTE**: El código del módulo (`BonusService`, `BonusPolicy`, controllers) importa de `App\Models\*`, NO de `Modules\Bonus\Models\*`. Esto es intencional porque los modelos de `app/Models/` son los canónicos.

### Sistema Multi-Tipo de Sesiones

Un bono puede tener múltiples tipos de sesión. Ejemplo:
- "Bono Fisio + Osteo" = 5 sesiones de Fisioterapia + 3 de Osteopatía = 8 totales
- Precio final: 200€

**Flujo:**
1. Se crea un `BonusType` (template) con las líneas en `appointment_type_bonus_type`
2. Al asignar un bono al paciente desde el template, se copian las líneas a `bonus_session_lines`
3. Al imputar una cita al bono, se decrementa `remaining_quantity` del tipo correspondiente
4. `remaining_sessions` se mantiene como contador global (denormalizado)

**Backward Compatibility:**
- Bonos existentes sin `session_lines` usan el contador global `remaining_sessions`
- Solo los nuevos bonos creados desde template tienen `session_lines`

## Domain Service — `BonusService`

Ubicación: `modules/Bonus/Services/BonusService.php` (464 líneas)
Implementa: `BonusConsumableInterface`

| Método | Descripción |
|---|---|
| `index(filters, clinicId)` | Listado paginado con filtros por estado/pago |
| `forPatient(patientId, clinicId)` | Bonos de un paciente (incluye session_lines) |
| `createForPatient(patientId, data, clinicId)` | Crear bono (copia session_lines si tiene template) |
| `useBonusForAppointment(bonusId, appointment)` | Consumir sesión (multi-type o global) |
| `restoreBonusIfCancelled(appointment)` | Restaurar sesión al cancelar cita |
| `deleteBonus(bonus)` | Eliminar (bloquea si tiene factura) |
| `expiring(clinicId)` | Bonos con 1 sesión restante |

### Flujo de Consumo Multi-Tipo

```
useBonusForAppointment(bonusId, appointment)
├── Verificar expiración
├── Verificar remaining_sessions > 0
├── Verificar no duplicado (misma appointment)
├── Si tiene session_lines:
│   ├── Buscar línea por appointment_type_id
│   ├── Verificar remaining_quantity > 0
│   └── Decrementar remaining_quantity
├── Decrementar remaining_sessions (global)
└── Crear BonusUsage con appointment_type_id
```

### Cómo Obtener el Servicio

```php
// CORRECTO — usar container (appointment.php, cualquier parte de la app)
$service = app(\Modules\Bonus\Services\BonusService::class);

// INCORRECTO — no hacer esto (código viejo eliminado)
$service = new \App\Services\Bonus\BonusService(); // NO EXISTE
```

## API Endpoints

### Bonus CRUD (auth:sanctum + clinic + check.subscription)

| Método | URI | Controller | Descripción |
|---|---|---|---|
| GET | `/api/bonuses` | `BonusController@index` | Listado con filtros |
| GET | `/api/bonuses/{bonus}` | `BonusController@show` | Detalle |
| PUT | `/api/bonuses/{bonus}` | `BonusController@update` | Actualizar |
| DELETE | `/api/bonuses/{bonus}` | `BonusController@destroy` | Eliminar |
| GET | `/api/bonuses/expiring` | `BonusController@expiring` | Bonos por agotar |
| POST | `/api/bonuses/{bonus}/invoice` | `BonusController@issueInvoice` | Facturar |

### Bonos por Paciente

| Método | URI | Controller |
|---|---|---|
| GET | `/api/patients/{patient}/bonuses` | `PatientBonusController@index` |
| POST | `/api/patients/{patient}/bonuses` | `PatientBonusController@store` |

### Tipos de Bono (Templates)

| Método | URI | Controller |
|---|---|---|
| GET | `/api/bonus-types` | `BonusTypeController@index` |
| POST | `/api/bonus-types` | `BonusTypeController@store` |
| PUT | `/api/bonus-types/{bonusType}` | `BonusTypeController@update` |
| DELETE | `/api/bonus-types/{bonusType}` | `BonusTypeController@destroy` |

## Frontend

### PatientBonuses.vue — "Asociar Bono"

Ubicación: `resources/js/components/PatientBonuses.vue`

**Flujo de asociación:**
1. Botón "Asociar Bono" en la sección de bonos del paciente
2. Modal con campo de búsqueda de templates (combobox con filtro por nombre)
3. Cada opción muestra: `3x Fisioterapia (50€) + 2x Psicología (60€) — Pack Bienestar — 120€`
4. Al seleccionar: pre-llena nombre, precio, expiración; muestra preview de líneas
5. Campos editables: nombre, precio, expiración
6. Submit → POST `/api/patients/{patient}/bonuses` con `bonus_type_id`

**Data source:** `GET /api/bonus-types` (retorna templates con `appointmentTypes` relationship)

**Empty state:** Si no hay templates → "No hay tipos de bonos creados. Crear en Servicios" → `/company-services?tab=bonos`

**Renovar:**
- Si el bono tenía `bonus_type_id` → reutiliza ese template automáticamente
- Si no → usuario debe buscar un template nuevo

### Company Services — Tab Bonos

Ubicación: `resources/js/views/company-services/Index.vue`

- Acepta query param `?tab=bonos` para abrir directamente el tab de Bonos
- Los templates de bonos se gestionan aquí (CRUD con líneas por tipo de sesión)

## Reglas de Negocio Clave

1. **Multi-type**: Si un bono tiene `session_lines`, el consumo descuenta del tipo específico
2. **Backward compat**: Bonos sin `session_lines` usan contador global
3. **Expiración**: Se verifica `expires_at` al consumir
4. **Duplicado**: No se puede usar el mismo bono dos veces para la misma cita
5. **Facturado**: No se puede eliminar un bono que tiene `invoice_id`
6. **Contador**: `counter` se auto-genera con formato `BO-XXXXXX`
7. **Template required**: Para crear un bono nuevo, SIEMPRE se debe seleccionar un template (no hay modo manual)

## Integración con Otros Módulos

| Módulo | Cómo integra |
|--------|-------------|
| **Appointments** | `Appointment::applyBonus()` llama a `app(BonusService::class)->useBonusForAppointment()` |
| **Invoicing** | `InvoicingService::issueForBonus()` crea factura desde bono |
| **Payments** | `PaymentService` valida relación bonus/pago |
| **Dashboard** | `DashboardSummaryService` computa métricas de bonos |
| **Company Services** | Templates de bonos se gestionan en `/company-services` tab Bonos |

## Tests

```
modules/Bonus/tests/
├── Feature/
│   ├── BonusMultiTypeTest.php           — Tests HTTP (expiring, index)
│   └── PatientBonusAssignmentTest.php   — Tests HTTP (assign bonus: owner, admin, manager)
└── Unit/
    └── BonusServiceTest.php             — Tests unitarios (5 tests)
```

### Ejecución

```bash
# Solo bonus (bounded context aislado)
php artisan test --testsuite=Bonus

# Tests específicos
php artisan test modules/Bonus/tests/Unit/BonusServiceTest.php
php artisan test modules/Bonus/tests/Feature/BonusMultiTypeTest.php
php artisan test modules/Bonus/tests/Feature/PatientBonusAssignmentTest.php
```

### Tests Unitarios (5)

| Test | Qué verifica |
|------|-------------|
| `create_bonus_manually` | Creación manual con `createForPatient()` |
| `create_bonus_from_template_creates_session_lines` | Template crea `BonusSessionLine` records |
| `delete_bonus_without_invoice` | Eliminar bono sin factura funciona |
| `delete_bonus_with_invoice_throws` | Eliminar bono facturado lanza `DomainException` |
| `for_patient_returns_session_lines` | `forPatient()` retorna líneas de sesión |

### Tests HTTP (4)

| Test | Qué verifica |
|------|-------------|
| `bonus_expiring_endpoint` | GET `/api/bonuses/expiring` con multi-type |
| `bonus_index_with_filters` | GET `/api/bonuses` con paginación |
| `owner_can_assign_bonus_to_patient` | POST bono como owner → 201 |
| `admin_can_assign_bonus_to_patient` | POST bono como admin → 201 |
| `manager_can_assign_bonus_to_patient` | POST bono como manager → 201 |
| `assigned_bonus_appears_in_patient_bonuses` | GET patient bonuses incluye el nuevo bono |

## Errores Comunes

| Error | Causa | Solución |
|-------|-------|---------|
| Tipo no incluido | `appointment_type_id` no existe en session_lines del bono | Verificar que el tipo esté en las líneas |
| Sin session_lines | Bonos viejos usan contador global | Correcto por backward compat |
| Template soft-deleted | BonusType usa SoftDeletes | Verificar `whereNull('deleted_at')` |
| Contador duplicado | Se genera automáticamente | No sobreescribir |
| FK constraint | `bonus_usages.invoice_id` FK a `documents` | Crear Document primero o testear sin DB write |
| 403 en tests HTTP | `Gate::authorize` + `ClinicScope` + route model binding | Usar `actingAs()` + tests sin model binding |

## Pitfalls Conocidos y Soluciones Aplicadas

### 1. 403 "This action is unauthorized" al asignar bono (CheckSubscriptionAccess)

**Problema:** Las rutas del módulo Bonus se cargan vía `loadRoutesFrom()` y NO heredan el grupo `api` que incluye `SubstituteBindings`. Sin este middleware, el parámetro `{patient}` no se resuelve como modelo, y `Gate::authorize('view', $patient)` falla.

**Solución:** Agregar `\Illuminate\Routing\Middleware\SubstituteBindings::class` al stack de middleware en `modules/Bonus/Routes/api.php:22`.

**Archivo:** `modules/Bonus/Routes/api.php`
```php
Route::middleware(['auth:sanctum', 'clinic', 'check.subscription', \Illuminate\Routing\Middleware\SubstituteBindings::class])->group(function () {
```

### 2. Session lines sin `appointment_type_name` al crear bono

**Problema:** `PatientBonusController::store()` retorna `$bonus->load('sessionLines.appointmentType')` que serializa con Eloquent (objeto `appointment_type` anidado). El frontend espera `appointment_type_name` (string directo). Al añadir el bono recién creado a la lista local vía `normalizeBonus()`, el template renderiza `undefined`.

**Solución:** En `normalizeBonus()` de `PatientBonuses.vue`, transformar `session_lines` para extraer `appointment_type_name` desde el objeto anidado:
```js
session_lines: Array.isArray(b.session_lines) ? b.session_lines.map(line => ({
  ...line,
  appointment_type_name: line.appointment_type_name ?? line.appointment_type?.description ?? '—',
})) : [],
```

### 3. `bonus_type_name` no se mostraba en la preview del bono

**Problema:** La serialización de `BonusService::forPatient()` no incluía `bonus_type_name`, y el template no lo mostraba.

**Solución:** Agregar `bonus_type_name` al mapeo en `BonusService::forPatient()` y mostrarlo en `PatientBonuses.vue`.
- Backend: `modules/Bonus/Services/BonusService.php` — incluir `'bonus_type_name' => $bonus->bonusType?->description`
- Frontend: `resources/js/components/PatientBonuses.vue` — agregar `<div v-if="b.bonus_type_name">` en el template y CSS `.bonus-type-name`
