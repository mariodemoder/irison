# Documentacion Irison (Indice)

Este directorio centraliza la documentacion oficial del backend y operaciones.

## Mapa principal

- `backend/`: flujos de autenticación, autorización y lógica de negocio del API.
- `backend/booking.md`: reserva online, flujo "Cualquier profesional", bloqueo del hueco, tests.
- `backend/notifications.md`: catálogo completo de notificaciones (22 emails, branding por audiencia, tests y formato).
- `backend/read-only-policy.md`: política de solo lectura post-trial (qué se permite, qué se bloquea, puntos de enforcement).
- `backend/reminder-scheduling.md`: jobs de recordatorios de citas (24h/2h), frecuencia variable vía `REMINDER_INTERVAL_MINUTES` e idempotencia.
- `backend/finance.md`: módulo de finanzas — gastos, tarifas por miembro del equipo y cálculo de beneficios.
- `backend/finance-restructure-spec.md`: spec completo de reestructuración del módulo Finanzas PRO (6 fases, roadmap de implementación).
- `specs/data-import-spec.md`: spec del módulo de Importación de datos CSV (PRO/Enterprise) — decisiones, arquitectura `modules/DataImport/`, API, frontend y checklist de retomada.
- `backoffice/`: arquitectura y operacion del panel interno.
- `backoffice/subscriptions.md`: comportamiento del ciclo de suscripcion y alertas internas.
- `backoffice/notificaciones-internas.md`: alertas internas de backoffice (tipos, reconciliacion, UI).
- `backoffice/usuario/`: manuales para usuarios internos de backoffice.
- `cliente/`: guias para cliente final (clinicas de Irison).
- `cliente/pacientes/`: guias operativas de pacientes para la clinica (incluye acceso al Portal del Paciente).
- `deployment/`: despliegue e infraestructura.
- `frontend/`: protocolos y guias para desarrollo frontend (Vue 3).
- `documentation-plan.md`: plan de informacion, ownership y roadmap de docs.
- `opencode-arnes.md`: estructura del harness opencode (agentes, skills, comandos) y cómo se cargan los skills.
- `agent-commands.md`: comandos de agentes (`con promp`, `con clean`, `con tests`).

## Reglas de mantenimiento

- Cada modulo nuevo debe incluir una seccion de uso operativo.
- Toda accion sensible debe documentar permisos, precondiciones y rollback.
- Si una ruta o flujo cambia, actualizar docs en el mismo PR.
- Evitar duplicacion de contenido: usar enlaces entre documentos.

## Audiencias

- Backoffice interno: soporte, billing, super_admin.
- Cliente Irison: owner de clinica y equipo operativo de la clinica.