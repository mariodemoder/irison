---
name: company-services
description: Use when working on the Servicios page: session types (cesiones), bonus types, booking settings panel (BookingSettings component), PatientBonuses component sidebar, /company-services route.
---

# Company Services Skill

## Core Files
- `app/Http/Controllers/Api/CompanyServicesController.php` — Backend controller (index + update)
- `app/Http/Controllers/Api/MeController.php` — `/me` endpoint (no longer includes cesiones/bonus_types)
- `routes/api.php` — `GET/PUT /api/company-services`
- `resources/js/views/company-services/Index.vue` — Frontend view
- `resources/js/router/index.js` — `/company-services` route
- `resources/js/layouts/MainLayout.vue` — Nav item "Servicios"

## Architecture
- CompanyServicesController handles crud for `appointment_types` (cesiones) and `bonus_types`
- Access: owner role or profile slug `admin`/`manager`
- The booking settings panel is rendered via `<BookingSettings>` child component
- Reads/writes use `/api/company-services`, not `/api/me`

## Key Changes
- Cesiones and bonus_types removed from `/api/me` GET and PUT
- Configuration.vue no longer has sesiones/bonos/booking tabs
- New standalone view at `/company-services` with Sesiones / Bonos / Reserva Online tabs

## Patient Bonuses — Show + Filter + Modal

`resources/js/components/PatientBonuses.vue` — Componente embed en `Show.vue` que lista bonos del paciente.

### Filtro default
- Por defecto (`showInactiveBonuses = false`) oculta solo bonos **pagados + agotados** `(b.is_paid && b.status === 'exhausted')`.
- El resto (activos, última sesión, impagos agotados, expirados) se ven siempre.
- Tooltip de lupa se actualiza dinámicamente: "Ocultar pagados y agotados" / "Ver todos los bonos".

### Modal asociar bono
- Botón "Asociar Bono" abre modal overlay (`modal-backdrop` + `modal-card` centrada + close button).
- Combobox con búsqueda: campo de texto filtra templates por nombre. Cada opción muestra: `3x Fisioterapia (50€) + 2x Psicología (60€) — Pack Bienestar — 120€`.
- Empty state: "No hay tipos de bonos creados. Crear en Servicios" → link a `/company-services?tab=bonos`.
- Al seleccionar template: pre-llena nombre, precio, expiración. Muestra preview de líneas de sesión (read-only).
- Campos editables: nombre, precio, expiración. Nº sesiones viene del template (no editable).
- Submit envía `bonus_type_id` al backend → `BonusService::createForPatient()` crea `BonusSessionLine` records.
- Renovar: si el bono tenía `bonus_type_id` → reutiliza ese template. Si no → usuario busca uno nuevo.
- Data source: `GET /api/bonus-types` (NO `/api/me`).

### Acciones por bono
- Renovar (si status=last), registrar pago, facturar, eliminar (bloqueado si `invoice_id` existe).
