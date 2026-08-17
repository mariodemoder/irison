# Documentacion Irison (Indice)

Este directorio centraliza la documentacion oficial del backend y operaciones.

## Mapa principal

- `backend/`: flujos de autenticación, autorización y lógica de negocio del API.
- `backend/booking.md`: reserva online, flujo "Cualquier profesional", bloqueo del hueco, tests.
- `backend/read-only-policy.md`: política de solo lectura post-trial (qué se permite, qué se bloquea, puntos de enforcement).
- `backend/reminder-scheduling.md`: jobs de recordatorios de citas (24h/2h), frecuencia variable vía `REMINDER_INTERVAL_MINUTES` e idempotencia.
- `backoffice/`: arquitectura y operacion del panel interno.
- `backoffice/subscriptions.md`: comportamiento del ciclo de suscripcion y alertas internas.
- `backoffice/notificaciones-internas.md`: alertas internas de backoffice (tipos, reconciliacion, UI).
- `backoffice/usuario/`: manuales para usuarios internos de backoffice.
- `cliente/`: guias para cliente final (clinicas de Irison).
- `deployment/`: despliegue e infraestructura.
- `frontend/`: protocolos y guias para desarrollo frontend (Vue 3).
- `documentation-plan.md`: plan de informacion, ownership y roadmap de docs.

## Reglas de mantenimiento

- Cada modulo nuevo debe incluir una seccion de uso operativo.
- Toda accion sensible debe documentar permisos, precondiciones y rollback.
- Si una ruta o flujo cambia, actualizar docs en el mismo PR.
- Evitar duplicacion de contenido: usar enlaces entre documentos.

## Audiencias

- Backoffice interno: soporte, billing, super_admin.
- Cliente Irison: owner de clinica y equipo operativo de la clinica.