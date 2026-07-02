# Skill Index — Irison

Router rápido para cargar solo el skill que necesitas.

## Core (read first)
- `core/index.md` — Quick start, architecture, conventions

## Auth / Autenticación
- `auth/index.md` — Role helpers, policies, registration, appointment/patient scoping, financial data stripping

## Backend
- `backend/index.md` — Business logging, soft deletes

## Frontend
- `frontend/index.md` — Error handling, button/popup styling, toast

## Billing
- `billing/index.md` — Stripe, subscriptions, webhooks, backoffice shortcuts

## Appointments
- `appointments/index.md` — Form, availability, date/time pitfalls, overlap, bonus/credit

## Consentimientos
- `consent.md` — Templates, digital signature, remote signing, PDF generation, patient embed layout

## Online Booking
- `booking/index.md` — Models, AvailabilityEngine, notifications, public vs admin routes

## QA
- `qa/index.md` — Testing strategies, coverage rules, delegation criteria

## Deployment
- `deployment/index.md` — Production checklist, migrations, queues

## Company Services
- `company-services/index.md` — Session types, bonus types, booking settings management

## Team / Equipo
- `team/index.md` — User management, profiles, professions, schedules, booking link

## Backoffice
- `backoffice/index.md` — Tenant management, Stripe customer sync, invoice resolution

---

## GitHub Agents (tool permissions)
Los `.agent.md` en `.github/agents/` definen permisos de herramientas, no contenido duplicado:
- `plan.agent.md` — Orchestrator
- `facturacion.agent.md` — Billing specialist
- `php-laravel-backend.agent.md` — Backend specialist
- `MISTER FRONT.agent.md` — Frontend specialist
- `irison-qa.agent.md` — QA specialist
- `Mr. DEPLOY.agent.md` — Deploy specialist
- `Mr. BackOffice.agent.md` — Backoffice specialist
