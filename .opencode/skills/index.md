# Skill Index — Irison

Router rápido para cargar solo el skill que necesitas. **Todos los skills son compartidos** por los agentes `plan` y `build`; se cargan por necesidad según el dominio de la tarea.

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
- `qa/index.md` — Testing strategies, coverage rules. Ejecución **solo on demand** (`con tests`) o si el plan aprobado define "complete flow"

## Clean (código muerto)
- Regla en `AGENTS.md` → `build` ejecuta dead-code cleanup **solo on demand** (`con clean`) o si el plan aprobado define "complete flow". Nunca automático, nunca borra tests.

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

## Skills nativos (SKILL.md en `.agents/skills/`)
Skills auto-descubiertos por opencode, compartidos por todos los agentes. Se cargan automáticamente según su descripción o bajo demanda:
- `vue-best-practices` — Composition API con `<script setup>` (Vue 3 SFC). Usar para todo trabajo `.vue`.
- `vite-patterns` — Config, plugins, HMR, env variables, build optimization.
- `frontend-a11y` — HTML semántico, ARIA, foco y navegación por teclado.
- `ui-to-vue` — Conversión de capturas/diseños a componentes Vue (Vant, Element Plus, Ant Design Vue).
