# Skill Index — Irison

Router rápido para cargar solo el skill que necesitas.

## Core (read first)
- `core/index.md` — Quick start, architecture, conventions

## Auth / Autenticación
- `auth/index.md` — Role helpers, policies, registration, appointment/patient scoping, financial data stripping

## Backend
- `backend/index.md` — Business logging, soft deletes

## Frontend
- `frontend/index.md` — Error handling, button/popup styling, toast, date picker protocol
- `frontend/menu-routing.md` — Menu nav items, `isActive()` rules, adding new items

## Billing
- `billing/index.md` — Stripe, subscriptions, webhooks, backoffice shortcuts

## Appointments
- `appointments/index.md` — Form, availability, date/time pitfalls, overlap, bonus/credit

## Bonos
- `bonus/index.md` — Multi-type sessions, BonusService, consumption flow, backward compatibility, session lines

## Consentimientos
- `consent.md` — Templates, digital signature, remote signing, PDF generation, patient embed layout

## Online Booking
- `booking/index.md` — Models, AvailabilityEngine, notifications, public vs admin routes

## QA
- `qa/index.md` — Testing strategies, coverage rules, delegation criteria

## Actividad
- `activity.md` — Registro de actividad (módulo DDD), cap de logins (3 por usuario/clínica) y ocultamiento de `login` al SPA

## Deployment
- `deployment/index.md` — Production checklist, migrations, queues

## Company Services
- `company-services/index.md` — Session types, bonus types, booking settings management

## Team / Equipo
- `team/index.md` — User management, profiles, professions, schedules, booking link

## Backoffice
- `backoffice/index.md` — Tenant management, Stripe customer sync, invoice resolution
- `backoffice/hard-delete.md` — Hard-delete funcional de clínica, manual y automatizado, preserve billing data
- `backoffice/upgrade-flow.md` — Flujo de upgrade por backoffice (`trial` vs `basic activo`), estados, webhooks y diagnóstico rápido

---

## GitHub Agents (tool permissions)
Los `.agent.md` en `.github/agents/` definen permisos de herramientas, no contenido duplicado:
- `plan.agent.md` — Orchestrator
- `billing.agent.md` — Billing specialist
- `backend.agent.md` — Backend specialist
- `frontend.agent.md` — Frontend specialist
- `qa.agent.md` — QA specialist
- `deploy.agent.md` — Deploy specialist
- `backoffice.agent.md` — Backoffice specialist
