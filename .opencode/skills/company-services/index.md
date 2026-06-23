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
