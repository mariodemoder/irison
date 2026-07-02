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

### Modal crear bono
- Formulario inline reemplazado por modal overlay (`modal-backdrop` + `modal-card` centrada + close button).
- Botón "Crear" cambia `showForm = true`.
- Modal incluye selector de plantilla (carga desde `/api/me` → `bonus_types`), campos nombre, nº sesiones, precio, expira.
- Al crear, el bono se inserta al inicio con clase `new` (highlight 4s).

### Acciones por bono
- Renovar (si status=last), registrar pago, facturar, eliminar (bloqueado si `invoice_id` existe).
