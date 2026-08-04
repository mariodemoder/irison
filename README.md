dd# Irison — SaaS de Gestión para Clínicas

Plataforma web moderna para la gestión integral de clínicas y centros de salud. Multi-tenancy, backend Laravel API, frontend SPA Vue 3 y base de datos PostgreSQL.

## Stack Tecnológico

- **Backend:** Laravel 12 / PHP 8.2+
- **Frontend:** Vue 3 + Vite 6 + Tailwind CSS
- **Base de datos:** PostgreSQL
- **Pagos:** Stripe + Laravel Cashier
- **PDFs:** Spatie Browsershot + Puppeteer
- **Auth:** Laravel Sanctum

## Funcionalidades

- **Agenda y citas** — Vista día/semana, tipos de sesión configurables, estados múltiples (programada, completada, cancelada, no-show), historial clínico por cita con PDF.
- **Pacientes** — Ficha completa con auto-numeración por clínica, imágenes, historial clínico cronológico, crédito disponible.
- **Bonos (paquetes de sesiones)** — Tipos de bono configurables, asignación a pacientes, control de consumos atómico, reversión automática al cancelar citas.
- **Facturación** — Facturas, abonos y facturas varias. IVA desglosado, numeración automática por tipo de documento, PDF con branding personalizado de la clínica.
- **Pagos** — Múltiples métodos (efectivo, tarjeta, transferencia), pagos parciales, vinculación a citas y bonos, y gestión de crédito.
- **Reserva online** — Widget público multi-paso: selección de servicio, profesional, fecha y hora en tiempo real. Confirmación y cancelación mediante token sin autenticación.
- **Equipo** — Gestión de usuarios, roles (owner, admin, manager, professional), profesiones personalizadas, horarios semanales y excepciones por fecha.
- **Recordatorios automáticos** — Envíos programados 24h y 2h antes de cada cita vía email, historial completo de notificaciones y reenvío.
- **Dashboard analítico** — Resumen diario de citas, producción financiera, alertas (bonos impagos, crédito pendiente, inactividad) y gráficos interactivos con Chart.js.
- **Suscripciones SaaS** — Planes con prueba gratuita, facturación recurrente vía Stripe, gestión de ciclo de vida (trial, activo, cancelado, read-only).
- **Multi-tenancy** — Aislamiento total por clínica. Datos, facturación y branding (logo, colores, fondo de facturas) completamente independientes.
- **Backoffice** — Panel administrador con roles (super_admin, support, billing, readonly) para gestionar todas las clínicas desde un solo lugar.

## Instalación Rápida

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurar base de datos PostgreSQL en `.env` y ejecutar:

```bash
php artisan migrate
npm run build
php artisan serve
```

## Tests

```bash
php artisan test
```
