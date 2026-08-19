# Finance Module — Spec de Reestructuracion (PRO)

> **Ultima actualizacion:** 2026-08-18
> **Estado:** Fase 0, Fase 1, Fase 2, Fase 3, Fase 4 y Fase 5 completadas. Fase 6 (Permisos y Polish) pendiente.
> **Objetivo:** Convertir el modulo de Finanzas en un sistema completo de gestion economica de la clinica PRO.

---

## 1. Estado actual

### 1.1 Backend — Lo que existe hoy

| Tabla | Descripcion | Estado |
|-------|-------------|--------|
| `payments` | Ledger de ingresos (citas, bonos, creditos, otros) | Completo |
| `expenses` | Gastos con categorias, impuestos, metodos de pago | Completo |
| `expense_categories` | Categorias de gasto por clinica | Completo |
| `professional_rates` | Coste/hora por profesional | Completo |
| `documents` | Facturas y abonos (rectificaciones) | Completo |
| `document_items` | Lineas de detalle de facturas | Completo |
| `bonuses` | Paquetes de sesiones con precio | Completo |
| `bonus_usages` | Consumo de sesiones de bonos | Completo |
| `credit_usages` | Uso de creditos (adelantos) | Completo |
| `products` | Catalogo de productos | Completo |
| `appointments` | Citas con payment_status, price, bonus_id, invoice_id | Completo |

### 1.2 Frontend

| Ruta | Vista | Descripcion |
|------|-------|-------------|
| `/finance` | `finance/Index.vue` | Pestanas: Pendientes, Gastos, Proveedores, Tarifas, Beneficios (~1100 lineas) |
| `/payments` | `payments/Index.vue` | Lista de pagos con filtros |
| `/payments/create` | `payments/Form.vue` | Formulario de creacion de pago |
| `/invoices` | `invoices/Index.vue` | Lista de facturas |
| `/invoices/create` | `invoices/Form.vue` | Creacion de facturas (varios) |

### 1.3 Arquitectura DDD del modulo Finance

```
modules/Finance/
  Domain/
    Contracts/     -> 5 interfaces (Benefits, Expense, ExpenseCategory, ProfessionalRate, Provider)
    Enums/         -> 1 (ExpensePaymentMethod)
    Models/        -> 4 value objects (Expense, ExpenseCategory, ProfessionalRate, Provider)
    Services/      -> 1 (MarginCalculator)
  Application/
    DTOs/          -> 4 (BenefitsReportData, ExpenseFilterData, PendingPaymentData, ProviderFilterData)
    UseCases/      -> 18 use cases (10 commands + 5 queries + 2 combined + 1 query+command)
  Infrastructure/
    Controllers/   -> 6 (Benefits, ExpenseCategory, Expense, PendingPayment, ProfessionalRate, Provider)
    Persistence/   -> 9 (4 repos + 1 data provider + 4 Eloquent models)
    Policies/      -> 5 (Benefits, ExpenseCategory, Expense, ProfessionalRate, Provider)
    Providers/     -> 1 (FinanceServiceProvider)
    Requests/      -> 7 (SaveProfessionalRate, Store/UpdateExpense, Store/UpdateExpenseCategory, Store/UpdateProvider)
  Config/          -> 1 (finance.php)
  Database/Migrations/ -> 5 (expense_categories, expenses, professional_rates, providers, provider_id FK)
  Routes/api.php     -> 18 endpoints (bajo pro.access)
  Tests/             -> 17 tests (todos pasando)
```

**Total archivos: 54**

### 1.4 Lo que el modulo NO tiene

- Deteccion de citas no pagadas (pendientes de cobro) — Fase 1
- Registro de ingresos manuales con UI dedicada
- Sistema de devoluciones con trazabilidad
- Dashboard ejecutivo con KPIs de resumen
- Informes exportables
- Permisos granulares por rol (solo owner/admin)
- Auditoria financiera

---

## 2. Arquitectura de referencia

### 2.1 Navegacion propuesta (al completar todas las fases)

```
FINANZAS (/finance)
|
+-- Resumen (primera pestana)
|   +-- KPIs: Ingresos, Gastos, Beneficio, Pendientes, Ticket medio, Margen
|   +-- Grafico evolucion mensual (12 meses)
|   +-- Tabla: Ingresos por metodo de pago
|   +-- Comparativa: Mes actual vs anterior
|
+-- Ingresos
|   +-- Tabla de pagos (lee de payments)
|   +-- Filtros: paciente, profesional, metodo, rango fechas
|   +-- Boton: Registrar ingreso manual
|   +-- Boton: Reembolsar (en cada pago)
|
+-- Pendientes
|   +-- Tabla de citas no pagadas
|   +-- Filtros: paciente, profesional, rango fechas
|   +-- Badge: n de pendientes en la pestana
|   +-- Accion: Registrar pago desde la fila
|
+-- Gastos
|   +-- Tabla de gastos (ya existe)
|   +-- Filtros: categoria, proveedor, rango fechas
|   +-- CRUD completo (ya existe)
|
+-- Tarifas
|   +-- Tabla de todos los miembros del equipo
|   +-- Editor inline de coste/hora (ya existe)
|
+-- Informes
|   +-- Selector de tipo: Ingresos, Gastos, Beneficio, Profesionales, Servicios
|   +-- Tabla dinamica segun tipo
|   +-- Exportar CSV / Excel
|
+-- Configuracion
    +-- Categorias de gasto (CRUD)
    +-- Proveedores (CRUD)
    +-- Metodos de pago (configurable)
```

### 2.2 Modelo de datos completo

```
EXISTENTES (no crear):
  payments              -> Ledger de ingresos
  expenses              -> Gastos
  expense_categories    -> Categorias de gasto
  professional_rates    -> Coste/hora profesional
  documents             -> Facturas y abonos
  appointments          -> Citas (con payment_status)
  bonuses               -> Bonos
  credit_usages         -> Creditos

NUEVOS (crear en fases indicadas):
  providers             -> Fase 2
```

---

## 3. Principios de diseño

### 3.1 Integrar, no duplicar

El modulo Finance LEE de `payments` para ingresos y de `expenses` para gastos. NO crea un ledger paralelo (`financial_transactions`). La tabla `payments` YA ES el registro financiero de ingresos.

### 3.2 Multi-tenancy obligatorio

Toda entidad financiera tiene `clinic_id`. Todas las consultas usan `BelongsToClinic` + `ClinicScope`. Nunca hacer `Model::find($id)` sin filtrar por clinica.

### 3.3 No eliminacion fisica

Los registros financieros nunca se borran fisicamente. Se anulan con `status = 'cancelled'` o se reembolsan con transacciones compensatorias.

### 3.4 Autorizacion por rol

- **Owner/Admin** (`hasFullAccess`): Acceso completo
- **Manager**: Ver todo, no modificar configuracion sensible
- **Receptionist**: Ver cobros, registrar pagos, ver pendientes
- **Professional**: Ver solo sus propios cobros

### 3.5 Capa por capa

```
Controller (HTTP) -> UseCase -> Repository -> Eloquent
                      |
               Domain Service (calculos puros)
                      |
               Policy (authorization)
```

---

## 4. Fase 0 — Quick Wins [COMPLETADA]

**Estado:** COMPLETADA
**Archivos modificados:** 7
**Tests:** 8 pasando (1 nuevo)

### Cambios realizados

| Archivo | Cambio |
|---------|--------|
| `BenefitsDataProviderInterface.php` | +2 metodos: `paidOperationsCount()`, `revenueByPaymentMethod()` |
| `BenefitsDataProvider.php` | Implementacion de ambos metodos |
| `BenefitsReportData.php` | +2 propiedades: `paidOperationsCount`, `revenueByPaymentMethod` |
| `BuildBenefitsReportQuery.php` | Llama nuevos metodos, incluye en comparativa temporal |
| `finance/Index.vue` | +2 tarjetas KPI, +1 tabla desglose, tooltips explicativos |
| `FinanceApiTest.php` | +1 test: `test_benefits_report_includes_new_kpis` |
| `docs/backend/finance.md` | Documentacion actualizada |

### KPIs anadidos

- **Ticket medio**: `revenue / paid_operations_count`
- **Operaciones pagadas**: Total de pagos completados (excluyendo refunds)

### Tabla anadida

- **Ingresos por metodo de pago**: Efectivo, Tarjeta, Transferencia — con conteo, total y porcentaje

---

## 5. Fase 1 — Pendientes de Cobro [COMPLETADA]

**Prioridad:** ALTA
**Dependencias:** Fase 0
**Estado:** COMPLETADA
**Tests:** 5 nuevos (13 total pasando)

### Cambios realizados

| Archivo | Tipo | Cambio |
|---------|------|--------|
| `ListPendingPaymentsQuery.php` | Nuevo | Use case de listado con filtros y paginacion |
| `RegisterPaymentFromPendingCommand.php` | Nuevo | Use case de registro de pago con validacion |
| `PendingPaymentData.php` | Nuevo | DTO de respuesta |
| `PendingPaymentController.php` | Nuevo | Controller HTTP con 2 endpoints |
| `PendingPaymentController.php` | Nuevo | Autorizacion via ExpensePolicy (viewAny) |
| `Routes/api.php` | Modificado | +2 endpoints: GET pending-payments, POST register-payment |
| `finance/Index.vue` | Modificado | +1 pestana "Pendientes" (default), +1 modal de pago, card full-height, help-btn + FinanceHelpModal |
| `FinanceHelpModal.vue` | Nuevo | Modal de ayuda con documentacion de los 8 KPIs |
| `FinanceApiTest.php` | Modificado | +5 tests de pendientes |
| `docs/backend/finance.md` | Modificado | Documentacion actualizada |

### Frontend adicional (no planeado originalmente)

- **Card full-height**: `.finance-page` flex container con `min-height: calc(100vh - 160px)` para que la card de Finanzas llegue hasta abajo con la misma altura en todas las pestanas
- **Tablas de beneficios enmarcadas**: `.benefits-grid .table-wrap` con borde, border-radius y background
- **FinanceHelpModal**: Modal de ayuda con `?` button en la barra de herramientas de beneficios, documenta los 8 KPIs, 4 tablas de desglose y la comparativa temporal

### 5.1 Objetivo

Detectar automaticamente las citas no pagadas o parcialmente pagadas y permitir registrar pagos desde una vista unificada.

### 5.2 Backend

#### 5.2.1 Nuevo use case: `ListPendingPaymentsQuery`

**Archivo:** `modules/Finance/Application/UseCases/ListPendingPaymentsQuery.php`

Logica:
- Consultar `appointments` con `payment_status IN ('pending', 'partially_paid')` y `status != 'canceled'`
- Filtrar por `clinic_id` (multi-tenancy)
- LEFT JOIN con `payments` para calcular `paid_amount` (suma de pagos completados)
- Calcular `pending_amount = price - paid_amount`
- Filtros opcionales: `patient_id`, `professional_id`, `from_date`, `to_date`
- Retornar lista paginada con: `appointment_id`, `patient_name`, `professional_name`, `appointment_date`, `service_name`, `price`, `paid_amount`, `pending_amount`, `payment_status`

#### 5.2.2 Nuevo use case: `RegisterPaymentFromPendingCommand`

**Archivo:** `modules/Finance/Application/UseCases/RegisterPaymentFromPendingCommand.php`

Logica:
- Recibe: `appointment_id`, `amount`, `method`, `notes` (opcional)
- Validar que el appointment pertenece a la clinica
- Validar que `amount <= pending_amount`
- Crear registro en `payments` con `concept = 'appointment'`, `status = 'completed'`
- Llamar a `AppointmentPendingPaymentService::syncPaymentStatus()` para actualizar el estado
- Retornar el pago creado

#### 5.2.3 Nuevo endpoint

```http
GET  /api/finance/pending-payments
POST /api/finance/pending-payments/{appointment}/register-payment
```

Ambos bajo middleware: `auth:sanctum`, `clinic`, `check.subscription`, `pro.access`

#### 5.2.4 Nuevo DTO: `PendingPaymentData`

**Archivo:** `modules/Finance/Application/DTOs/PendingPaymentData.php`

Campos:
- `appointmentId: int`
- `patientName: string`
- `professionalName: string`
- `appointmentDate: CarbonInterface`
- `serviceName: string`
- `price: float`
- `paidAmount: float`
- `pendingAmount: float`
- `paymentStatus: string`

#### 5.2.5 Nueva Policy: `PendingPaymentPolicy`

**Archivo:** `modules/Finance/Infrastructure/Policies/PendingPaymentPolicy.php`

- `viewAny`: `hasFullAccess() || hasOperationalAccess()` (owner, admin, reception)
- `create`: `hasFullAccess() || hasOperationalAccess()`

#### 5.2.6 Registrar en FinanceServiceProvider

- Bind `ListPendingPaymentsQuery`
- Bind `RegisterPaymentFromPendingCommand`
- Registrar policy
- Registrar rutas

### 5.3 Frontend

#### 5.3.1 Nueva pestana "Pendientes"

**Archivo:** `resources/js/views/finance/Index.vue` (anadir pestana)

Componentes:
- Barra de filtros: selector paciente, selector profesional, rango fechas
- Tabla con columnas: Paciente, Profesional, Fecha, Servicio, Importe, Pagado, Pendiente, Accion
- Boton "Registrar pago" en cada fila que abre modal
- Badge con numero de pendientes en el header de la pestana

#### 5.3.2 Modal "Registrar pago"

Campos:
- Importe (pre-rellenado con pending_amount)
- Metodo de pago (select: Efectivo, Tarjeta, Transferencia)
- Notas (opcional)
- Boton "Registrar"

#### 5.3.3 Estados visuales

- Pendiente completo: badge rojo
- Parcialmente pagado: badge amarillo
- Pagado: badge verde (no aparece en la lista)

### 5.4 Tests

```php
public function test_list_pending_payments(): void
{
    // Crear appointment con payment_status = 'pending'
    // Verificar que aparece en la lista
    // Verificar campos calculados
}

public function test_register_payment_from_pending(): void
{
    // Crear appointment pendiente de 80 EUR
    // Registrar pago de 50 EUR
    // Verificar que payment_status cambia a 'partially_paid'
    // Verificar que el pago se crea en payments
}

public function test_cannot_overpay_pending(): void
{
    // Crear appointment pendiente de 80 EUR
    // Intentar registrar pago de 100 EUR
    // Verificar que retorna error
}
```

### 5.5 Archivos a crear/modificar

| Archivo | Tipo | Descripcion |
|---------|------|-------------|
| `ListPendingPaymentsQuery.php` | Nuevo | Use case de listado |
| `RegisterPaymentFromPendingCommand.php` | Nuevo | Use case de registro de pago |
| `PendingPaymentData.php` | Nuevo | DTO de respuesta |
| `PendingPaymentPolicy.php` | Nuevo | Autorizacion |
| `PendingPaymentController.php` | Nuevo | Controller HTTP |
| `FinanceServiceProvider.php` | Modificar | Bindings + rutas |
| `FinanceRoutes/api.php` | Modificar | Nuevas rutas |
| `finance/Index.vue` | Modificar | Nueva pestana + modal |
| `FinanceApiTest.php` | Modificar | +3 tests |

---

## 6. Fase 2 — Proveedores [COMPLETADA]

**Prioridad:** MEDIA
**Estado:** COMPLETADA
**Tests:** 4 nuevos (17 total pasando)

### Cambios realizados

| Archivo | Tipo | Cambio |
|---------|------|--------|
| `create_providers_table.php` | Nuevo | Migration tabla providers con UNIQUE(clinic_id, name) |
| `add_provider_id_to_expenses_table.php` | Nuevo | FK provider_id en expenses |
| `Provider.php` | Nuevo | Domain model con 9 propiedades |
| `ProviderRepositoryInterface.php` | Nuevo | Contract con 4 metodos |
| `ProviderEloquentModel.php` | Nuevo | Eloquent model con BelongsToClinic |
| `ProviderRepository.php` | Nuevo | Implementacion del contract |
| `ProviderController.php` | Nuevo | Controller HTTP con 4 endpoints (CRUD) |
| `ProviderPolicy.php` | Nuevo | Autorizacion hasFullAccess |
| `StoreProviderRequest.php` | Nuevo | Validacion con unique por clinica |
| `UpdateProviderRequest.php` | Nuevo | Validacion con unique + ignore |
| `ListProvidersQuery.php` | Nuevo | Use case de listado |
| `CreateProviderCommand.php` | Nuevo | Use case de creacion |
| `UpdateProviderCommand.php` | Nuevo | Use case de actualizacion |
| `DeleteProviderCommand.php` | Nuevo | Use case de eliminacion |
| `ProviderFilterData.php` | Nuevo | DTO de respuesta |
| `ExpenseEloquentModel.php` | Modificado | +provider_id fillable, +provider() relation |
| `ExpenseRepository.php` | Modificado | Eager load provider, filter by provider_id, store/update |
| `Expense.php` | Modificado | +providerId propiedad |
| `StoreExpenseRequest.php` | Modificado | +provider_id validation |
| `UpdateExpenseRequest.php` | Modificado | +provider_id validation |
| `ExpenseController.php` | Modificado | +provider_id filter validation |
| `ExpenseFilterData.php` | Modificado | +providerId propiedad |
| `FinanceServiceProvider.php` | Modificado | +ProviderRepositoryInterface binding |
| `AuthServiceProvider.php` | Modificado | +ProviderEloquentModel => ProviderPolicy |
| `Routes/api.php` | Modificado | +4 provider endpoints |
| `finance/Index.vue` | Modificado | +Pestana Proveedores, +selector proveedor en gastos, +filtro proveedor |
| `FinanceApiTest.php` | Modificado | +4 tests providers |

### 6.1 Objetivo

Enriquecer la gestion de gastos asociandolos a proveedores, permitiendo analisis por proveedor.

### 6.2 Backend

#### 6.2.1 Nueva tabla: `providers`

```sql
CREATE TABLE providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    nif VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_clinic_name (clinic_id, name)
);
```

#### 6.2.2 Nuevos archivos en modulo Finance

```
Domain/
  Contracts/ProviderRepositoryInterface.php
  Models/Provider.php
Infrastructure/
  Controllers/ProviderController.php
  Persistence/ProviderEloquentModel.php
  Persistence/ProviderRepository.php
  Policies/ProviderPolicy.php
  Requests/StoreProviderRequest.php
  Requests/UpdateProviderRequest.php
Application/
  UseCases/ListProvidersQuery.php
  UseCases/CreateProviderCommand.php
  UseCases/UpdateProviderCommand.php
  UseCases/DeleteProviderCommand.php
Database/Migrations/2026_08_XX_create_providers_table.php
```

#### 6.2.3 Migration: anadir `provider_id` a `expenses`

```sql
ALTER TABLE expenses ADD COLUMN provider_id BIGINT UNSIGNED NULL AFTER category_id;
ALTER TABLE expenses ADD FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL;
```

#### 6.2.4 Endpoints

```http
GET    /api/finance/providers          -> ListProvidersQuery
POST   /api/finance/providers          -> CreateProviderCommand
PUT    /api/finance/providers/{id}     -> UpdateProviderCommand
DELETE /api/finance/providers/{id}     -> DeleteProviderCommand
```

#### 6.2.5 Actualizar ExpenseRepository

- `paginate()`: Anadir filtro `provider_id`
- `store()`: Anadir campo `provider_id`
- `update()`: Anadir campo `provider_id`
- `toDomain()`: Incluir `provider_id`

### 6.3 Frontend

#### 6.3.1 Filtro por proveedor en pestana Gastos

Anadir selector de proveedor en la barra de filtros existente.

#### 6.3.2 Columna "Proveedor" en tabla de gastos

Mostrar nombre del proveedor en cada fila.

#### 6.3.3 Selector de proveedor en modal de gasto

Anadir campo select con proveedores disponibles (cargados via API).

#### 6.3.4 CRUD de proveedores

Opcion A: Sub-pestana dentro de Gastos
Opcion B: Seccion en Configuracion de Finanzas

Recomendada: Opcion B (mantener Gastos limpio).

### 6.4 Tests

- CRUD completo de proveedores
- Gastos con proveedor asociado
- Filtro de gastos por proveedor
- Proveedor no puede pertenecer a otra clinica

---

## 7. Fase 3 — Ingresos y Devoluciones

**Prioridad:** MEDIA
**Esfuerzo estimado:** 2-3 dias
**Dependencias:** Fase 1

### 7.1 Objetivo

Permitir registrar ingresos que no provienen de citas y gestionar devoluciones con trazabilidad completa.

### 7.2 Backend

#### 7.2.1 Nuevo use case: `RegisterManualIncomeCommand`

**Archivo:** `modules/Finance/Application/UseCases/RegisterManualIncomeCommand.php`

Logica:
- Recibe: `amount`, `method`, `description`, `professional_id` (opcional), `date`, `patient_id` (opcional)
- Validar monto > 0
- Validar metodo de pago valido
- Crear registro en `payments` con:
  - `concept = 'other'`
  - `status = 'completed'`
  - `paid_at = date`
  - `professional_id` (si se proporciona)
  - `patient_id` (si se proporciona)
- Retornar el pago creado

#### 7.2.2 Nuevo use case: `RefundPaymentCommand`

**Archivo:** `modules/Finance/Application/UseCases/RefundPaymentCommand.php`

Logica:
- Recibe: `payment_id`, `amount` (opcional, default = total del pago), `reason`
- Validar que el pago pertenece a la clinica
- Validar que el pago no esta ya reembolsado
- Opcion A (recomendada): Actualizar `status` del pago existente a `refunded`, anadir campo `refund_reason` y `refunded_at`
- Opcion B: Crear pago nuevo con `concept = 'refund'` y monto negativo
- **Decision: Usar Opcion A** (mas limpio, menor duplicacion)
- Anadir columnas a `payments`: `refund_reason` (nullable), `refunded_at` (nullable)
- Retornar el pago actualizado

#### 7.2.3 Nuevos endpoints

```http
POST /api/finance/income                  -> RegisterManualIncomeCommand
POST /api/finance/payments/{id}/refund    -> RefundPaymentCommand
```

#### 7.2.4 Migration: anadir campos de refund a `payments`

```sql
ALTER TABLE payments ADD COLUMN refund_reason VARCHAR(500) NULL;
ALTER TABLE payments ADD COLUMN refunded_at TIMESTAMP NULL;
```

### 7.3 Frontend

#### 7.3.1 Nueva pestana "Ingresos"

- Tabla de pagos (lee de `payments` con `status = 'completed'`)
- Columnas: Fecha, Paciente, Concepto, Profesional, Metodo, Importe, Estado
- Filtros: paciente, profesional, metodo, rango fechas, concepto
- Boton "Registrar ingreso manual" -> modal
- Boton "Reembolsar" en cada fila -> modal con motivo
- Badge "Reembolsado" en pagos devueltos

#### 7.3.2 Modal "Registrar ingreso manual"

Campos:
- Concepto (texto libre)
- Importe
- Metodo de pago
- Profesional (opcional, select)
- Paciente (opcional, select)
- Fecha
- Notas (opcional)

#### 7.3.3 Modal "Reembolsar"

Campos:
- Importe a reembolsar (pre-rellenado con total del pago)
- Motivo del reembolso (requerido)
- Boton "Confirmar reembolso"

### 7.4 Tests

- Registrar ingreso manual -> se crea en payments con concept='other'
- Reembolsar pago -> status cambia a 'refunded'
- No se puede reembolsar un pago ya reembolsado
- El reembolso aparece en el reporte de beneficios

---

## 8. Fase 4 — Dashboard Ejecutivo

**Prioridad:** ALTA
**Esfuerzo estimado:** 2-3 dias
**Dependencias:** Fases 0, 1, 3

### 8.1 Objetivo

Crear una pantalla tipo "Resumen financiero" como PRIMERA pestana de /finance, con KPIs ejecutivos y grafico de evolucion.

### 8.2 Backend

#### 8.2.1 Nuevo endpoint: `GET /api/finance/summary`

Retorna:
```json
{
  "data": {
    "current_period": {
      "revenue": 12450,
      "expenses": 4320,
      "labor_cost": 2100,
      "profit": 6030,
      "margin_percentage": 48.4,
      "ticket_medio": 43.0,
      "paid_operations_count": 290,
      "pending_count": 12,
      "pending_amount": 780
    },
    "previous_period": {
      "revenue": 11200,
      "expenses": 4100,
      "profit": 5000,
      "margin_percentage": 44.6
    },
    "variation": {
      "revenue": 11.2,
      "expenses": 5.4,
      "profit": 20.6,
      "margin_percentage": 3.8
    },
    "evolution": [
      { "month": "2025-09", "revenue": 9800, "expenses": 3900, "profit": 4200 },
      { "month": "2025-10", "revenue": 10200, "expenses": 4000, "profit": 4500 },
      ...
    ],
    "by_payment_method": [
      { "method": "cash", "label": "Efectivo", "total": 3200, "percentage": 25.7 },
      { "method": "card", "label": "Tarjeta", "total": 7800, "percentage": 62.6 },
      { "method": "transfer", "label": "Transferencia", "total": 1450, "percentage": 11.7 }
    ]
  }
}
```

#### 8.2.2 Nuevo use case: `BuildFinanceSummaryQuery`

**Archivo:** `modules/Finance/Application/UseCases/BuildFinanceSummaryQuery.php`

Logica:
- Reutilizar `BenefitsDataProvider` para metricas del periodo actual
- Calcular periodo anterior (mismo tamano, ventana inmediatamente anterior)
- Calcular variaciones porcentuales
- Obtener evolucion ultimos 12 meses (un registro por mes)
- Obtener distribucion por metodo de pago
- Obtener conteo de pendientes de cobro

#### 8.2.3 Nuevo en BenefitsDataProvider

```php
public function pendingPaymentsCount(int $clinicId): int;
public function pendingPaymentsAmount(int $clinicId): float;
public function revenueEvolution(int $clinicId, int $months = 12): array;
```

### 8.3 Frontend

#### 8.3.1 Reestructurar pestanas

```
pestanas = [
  { key: 'resumen', label: 'Resumen' },      // NUEVA (primera)
  { key: 'ingresos', label: 'Ingresos' },     // NUEVA
  { key: 'pendientes', label: 'Pendientes' }, // NUEVA (Fase 1)
  { key: 'gastos', label: 'Gastos' },         // EXISTENTE
  { key: 'tarifas', label: 'Tarifas' },       // EXISTENTE
  { key: 'beneficios', label: 'Beneficios' }, // EXISTENTE (se mantiene como detalle)
  { key: 'informes', label: 'Informes' },     // Fase 5
  { key: 'config', label: 'Configuracion' },  // Fase 2/6
]
```

#### 8.3.2 Pestana Resumen

Layout:
- **Fila 1:** 6 tarjetas KPI (Ingresos, Gastos, Beneficio, Pendientes, Ticket medio, Margen)
  - Cada tarjeta con valor, variacion vs mes anterior, tooltip explicativo
- **Fila 2:** Grafico de evolucion (linea o barras, ultimos 12 meses)
  - Lineas: Ingresos (azul), Gastos (rojo), Beneficio (verde)
- **Fila 3:** 2 columnas
  - Izquierda: Tabla "Ingresos por metodo de pago"
  - Derecha: Resumen "Mes actual vs anterior"

#### 8.3.3 Grafico de evolucion

Usar una libreria ligera. Opciones:
- **Chart.js** (via `vue-chartjs`): ~60KB, muy comun, buena documentacion
- **CSS-only** (barras con divs): 0 dependencias, menos interactivo

**Decision recomendada:** Chart.js via `vue-chartjs` — industria estandard, mantenible.

Instalacion:
```bash
npm install chart.js vue-chartjs
```

### 8.4 Tests

- El endpoint `/finance/summary` retorna todos los campos esperados
- La evolucion tiene 12 registros (o menos si la clinica es nueva)
- Las variaciones se calculan correctamente

---

## 9. Fase 5 — Informes Exportables

**Prioridad:** MEDIA
**Esfuerzo estimado:** 2-3 dias
**Dependencias:** Fases 0, 1, 3

### 9.1 Objetivo

Generar informes detallados por diferentes dimensiones con capacidad de exportacion a CSV/Excel.

### 9.2 Backend

#### 9.2.1 Nuevo use case: `GenerateFinanceReportQuery`

**Archivo:** `modules/Finance/Application/UseCases/GenerateFinanceReportQuery.php`

Tipos de informe:
- `income` — Detalle de ingresos por dia/concepto
- `expenses` — Detalle de gastos por dia/categoria/proveedor
- `profit` — Beneficio diario/mensual
- `professional` — Ingresos y coste laboral por profesional
- `service` — Ingresos por tipo de servicio

Parametros:
- `type: string` (uno de los anteriores)
- `from_date: ?string`
- `to_date: ?string`
- `group_by: string` (day, week, month)

Retorna:
```json
{
  "data": {
    "type": "income",
    "period": { "from": "2026-08-01", "to": "2026-08-31" },
    "headers": ["Fecha", "Concepto", "Paciente", "Importe", "Metodo"],
    "rows": [
      ["2026-08-01", "Consulta", "Juan Perez", "45.00", "Tarjeta"],
      ...
    ],
    "summary": {
      "total": 12450.00,
      "count": 290
    }
  }
}
```

#### 9.2.2 Endpoint de exportacion

```http
GET /api/finance/reports/{type}?from_date=...&to_date=...&group_by=day&format=csv
GET /api/finance/reports/{type}?from_date=...&to_date=...&group_by=day&format=xlsx
```

Para CSV: Retornar `Content-Type: text/csv` con `Content-Disposition: attachment`
Para XLSX: Usar libreria `maatwebsite/excel` o `phpoffice/phpspreadsheet`

#### 9.2.3 Decision: XLSX

Para el MVP, CSV es suficiente. XLSX se puede anadir despues si los clientes lo piden.

### 9.3 Frontend

#### 9.3.1 Pestana "Informes"

Layout:
- Selector de tipo de informe (tabs o dropdown)
- Filtros: rango de fechas, agrupacion
- Tabla dinamica segun tipo seleccionado
- Boton "Exportar CSV" / "Exportar Excel"

### 9.4 Tests

- Generar cada tipo de informe
- Verificar headers y rows
- Verificar exportacion CSV

---

## 10. Fase 6 — Permisos y Polish

**Prioridad:** MEDIA
**Esfuerzo estimado:** 1-2 dias
**Dependencias:** Todas las fases anteriores

### 10.1 Objetivo

Refinar el control de acceso por rol y pulir la experiencia de usuario.

### 10.2 Backend

#### 10.2.1 Nuevo Gate/Permiso: `finance.access`

Registrar en `AuthServiceProvider`:

```php
Gate::define('finance.access', function (User $user) {
    return $user->hasFullAccess() || $user->hasOperationalAccess();
});
```

#### 10.2.2 Actualizar Policies existentes

Cambiar `viewAny` en todas las policies de Finance:

```php
// ANTES
public function viewAny(User $user): bool
{
    return (bool) $user->clinic_id && $user->hasFullAccess();
}

// DESPUES
public function viewAny(User $user): bool
{
    return (bool) $user->clinic_id && ($user->hasFullAccess() || $user->hasOperationalAccess());
}
```

#### 10.2.3 Nuevas restricciones

| Rol | Ver Resumen | Ver Ingresos | Ver Gastos | Ver Pendientes | Registrar pagos | Crear gastos | Config |
|-----|-------------|--------------|------------|----------------|-----------------|--------------|--------|
| Owner/Admin | Si | Si | Si | Si | Si | Si | Si |
| Manager | Si | Si | Si | Si | Si | Si | No |
| Receptionist | Si | Si | No | Si | Si | No | No |
| Professional | Si | Solo los suyos | No | No | No | No | No |

#### 10.2.4 Filtro por profesional

Los profesionales solo ven sus propios cobros. Implementar filtro automático:

```php
// En BenefitsDataProvider y otros queries
if ($user->isProfessional()) {
    $query->where('professional_id', $user->id);
}
```

### 10.3 Frontend

#### 10.3.1 Ocultar/mostrar pestanas segun permisos

```js
const visibleTabs = computed(() => {
  return tabs.filter(tab => {
    if (tab.key === 'gastos' && !hasFullAccess) return false
    if (tab.key === 'config' && !hasFullAccess) return false
    return true
  })
})
```

#### 10.3.2 Deshabilitar botones segun permisos

- Boton "Nuevo gasto": Solo owner/admin/manager
- Boton "Configuracion": Solo owner/admin
- Boton "Reembolsar": Solo owner/admin

#### 10.3.3 Mensajes claros

Cuando un usuario no tiene acceso a una seccion, mostrar mensaje explicativo en vez de simplemente ocultar.

### 10.4 Tests

- Profesional no puede ver gastos
- Receptionist no puede crear gastos
- Profesional solo ve sus propios pagos
- Owner puede todo

---

## 11. Modelo de datos consolidado

### Tablas existentes (no modificar significativamente)

```
payments
  id, clinic_id, patient_id, professional_id, concept, appointment_id,
  package_id, amount, method, status, counter, notes, paid_at,
  refund_reason (Fase 3), refunded_at (Fase 3)

expenses
  id, clinic_id, category_id, provider_id (Fase 2), concept, supplier,
  amount, tax_rate, total, date, payment_method, receipt_number, notes

expense_categories
  id, clinic_id, name, color, description

professional_rates
  id, clinic_id, user_id, cost_per_hour
```

### Tablas nuevas

```
providers (Fase 2)
  id, clinic_id, name, nif, email, phone, address, notes, timestamps
  UNIQUE(clinic_id, name)
```

### Relaciones

```
Clinic
  +-- Payments (1:N)
  +-- Expenses (1:N)
  +-- ExpenseCategories (1:N)
  +-- ProfessionalRates (1:N)
  +-- Providers (1:N, Fase 2)

Payment
  +-- Patient (N:1)
  +-- Professional -> User (N:1)
  +-- Appointment (N:1)
  +-- Package -> Bonus (N:1)
  +-- Clinic (N:1)

Expense
  +-- Category -> ExpenseCategory (N:1)
  +-- Provider (N:1, Fase 2)
  +-- Clinic (N:1)
```

---

## 12. Endpoints API

### Existentes (no cambiar)

```
GET    /api/finance/expense-categories
POST   /api/finance/expense-categories
PUT    /api/finance/expense-categories/{category}
DELETE /api/finance/expense-categories/{category}

GET    /api/finance/expenses
POST   /api/finance/expenses
GET    /api/finance/expenses/{expense}
PUT    /api/finance/expenses/{expense}
DELETE /api/finance/expenses/{expense}

GET    /api/finance/professional-rates
PUT    /api/finance/professional-rates/{user}

GET    /api/finance/benefits
```

### Nuevos por fase

**Fase 1:**
```
GET    /api/finance/pending-payments
POST   /api/finance/pending-payments/{appointment}/register-payment
```

**Fase 2:**
```
GET    /api/finance/providers
POST   /api/finance/providers
PUT    /api/finance/providers/{provider}
DELETE /api/finance/providers/{provider}
```

**Fase 3:**
```
POST   /api/finance/income
POST   /api/finance/payments/{id}/refund
```

**Fase 4:**
```
GET    /api/finance/summary
```

**Fase 5:**
```
GET    /api/finance/reports/{type}
GET    /api/finance/reports/{type}/export
```

---

## 13. Decisiones arquitectonicas

### 13.1 Por que NO `financial_transactions`

La tabla `payments` YA ES un ledger financiero de ingresos:
- Tiene `concept` (appointment, package, credit, other) = tipo + categoria
- Tiene `method` (cash, card, transfer) = metodo de pago
- Tiene `status` (completed, pending, refunded) = estado
- Tiene `patient_id`, `professional_id`, `appointment_id`, `package_id` = links a entidades
- Tiene `clinic_id` = multi-tenancy

Crear una tabla `financial_transactions` paralela seria:
- Duplicar datos
- Duplicar logica
- Crear inconsistencias
- Aumentar complejidad innecesariamente

### 13.2 Por que NO caja (cash register)

La mayoria de clinicas NO necesitan control de caja:
- Es feature de ERP/contabilidad
- Aumenta complejidad operativa
- Los metodos de pago ya dan visibilidad
- Se puede anadir como feature Enterprise despues

### 13.3 Por que NO Stripe para pagos de pacientes

- No hay e-commerce en Irison
- Los pagos son presenciales (efectivo/tarjeta/transferencia)
- Stripe solo se usa para suscripciones SaaS
- Integrar Stripe para pagos de clinica requiere:
  - Terminal Stripe o link de pago
  - Webhooks adicionales
  - Complejidad de conciliacion
  - Esto es feature Enterprise

### 13.4 Por que Chart.js para graficos

- Industria estandard para graficos web
- Buena documentacion y comunidad
- ~60KB gzipped (aceptable)
- vue-chartjs facilita integracion con Vue 3
- Alternativa: CSS-only (menos interactivo)

---

## 14. Lo que NO se implementa

Para mantener Irison como herramienta de clinica (no ERP):

- Contabilidad de partida doble
- Libro mayor / Balance contable
- Cuenta de resultados fiscal completa
- Modelos tributarios / presentacion de impuestos
- Nomina
- Contabilidad bancaria completa
- Facturacion electronica avanzada / SII
- Integracion bancaria compleja (Open Banking)
- Conciliacion bancaria automatica
- Control de caja (cash register)

Esto puede convertirse en un **modulo financiero/contable avanzado de ENTERPRISE** en el futuro.

---

## 15. Checklist de seguimiento

Usar este checklist para seguir el progreso entre sesiones:

- [x] **Fase 0:** Quick Wins — ticket medio, operaciones pagadas, ingresos por metodo de pago
- [x] **Fase 1:** Pendientes de Cobro — detectar y gestionar citas no pagadas + FinanceHelpModal + card full-height
- [x] **Fase 2:** Proveedores — entidad CRUD + asociar a gastos + selector en formulario + filtro por proveedor + validacion unica por clinica
- [x] **Fase 3:** Ingresos y Devoluciones — registro manual + reembolsos + abono opcional
- [x] **Fase 4:** Dashboard Ejecutivo — pestana Resumen con KPIs + grafico evolucion + metodos de pago
- [x] **Fase 5:** Informes Exportables — reportes por dimension + CSV
- [ ] **Fase 6:** Permisos y Polish — control por rol + UX

### Orden de implementacion recomendado

```
Fase 0 (completada)
    |
    +---> Fase 1 (pendientes) ---> Fase 3 (ingresos/devoluciones)
    |                                    |
    +---> Fase 2 (proveedores)          |
    |                                    |
    +---> Fase 4 (dashboard) <----------+
              |
              +---> Fase 5 (informes)
              |
              +---> Fase 6 (permisos)
```

Las fases 1, 2 y 3 pueden ejecutarse en paralelo.
La fase 4 requiere fases 1 y 3 completadas.
Las fases 5 y 6 van al final.
