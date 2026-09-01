# Skill Index — Irison

Mapa humano de los skills de dominio del proyecto. **Todos son skills opencode reales** (`**/SKILL.md` con frontmatter `name`/`description`), auto-descubiertos por opencode y cargables con la herramienta `skill`. Los agentes `plan` y `build` los cargan por necesidad según el dominio de la tarea — este índice es solo referencia para humanos.

## Skills de dominio (proyecto, `.opencode/skills/`)

| Skill | Cuándo cargarlo |
|---|---|
| `core` | Base de contexto para cualquier tarea en Irison: setup, arquitectura, convenciones, auth recovery |
| `backend` | Lógica backend, logging de negocio, soft deletes, pitfalls de DB |
| `frontend` | Cualquier trabajo Vue/UI en el SPA: errores, botones, popups, date picker, menú |
| `auth` | Autenticación/autorización, Sanctum, roles (admin/manager/professional), policies, registro |
| `billing` | Stripe, suscripciones, webhooks, upgrade/prorateo, solo lectura post-trial, backup XLSX |
| `appointments` | Form de citas, disponibilidad, solapamiento, date/time payload, consumo de bonos |
| `bonus` | Bonos multi-tipo, session lines, BonusService, flujo de consumo |
| `booking` | Reserva online: AvailabilityEngine, PublicBookingService, horarios, rutas públicas/admin |
| `consent` | Consentimientos informados: plantillas, firma digital, envío remoto, PDF |
| `activity` | Registro de actividad (activity_logs), cap de logins, logins ocultos al SPA |
| `deployment` | Despliegue: queues, migraciones, producción |
| `company-services` | Sesiones (cesiones), tipos de bono, panel reserva online (Servicios) |
| `qa` | Tests/QA **solo a demanda** (`con tests`) o "complete flow" |
| `team` | Gestión de equipo: usuarios, perfiles, profesiones, horarios, booking link |
| `backoffice` | Panel interno: admin_users, tenant management, upgrade flow, hard-delete |

Sub-recursos por dominio (referenciados relativos desde el `SKILL.md`):
- `backoffice/upgrade-flow.md` — flujo completo de upgrade (`trial` vs activo pagado)
- `backoffice/hard-delete.md` — hard-delete funcional de clínica
- `frontend/menu-routing.md` — reglas `isActive()` del menú `MainLayout.vue`

## Skills nativos vendored (`.agents/skills/`)

Skills de terceros auto-descubiertos, compartidos por todos los agentes, foco frontend:
- `vue-best-practices` — Composition API con `<script setup>` (Vue 3 SFC). Usar para todo trabajo `.vue`.
- `vite-patterns` — Config, plugins, HMR, env variables, build optimization.
- `frontend-a11y` — HTML semántico, ARIA, foco y navegación por teclado.
- `ui-to-vue` — Conversión de capturas/diseños a componentes Vue (Vant, Element Plus, Ant Design Vue).