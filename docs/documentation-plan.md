# Plan de Documentacion (Irison / Backoffice)

## Objetivo

Definir una arquitectura simple y sostenible para documentacion de:
- usuario interno de backoffice
- cliente final de Irison

## Ubicacion oficial

1. Documentacion interna de operacion (staff):
   - `docs/backoffice/usuario/`

2. Documentacion para cliente final (clinicas):
   - `docs/cliente/`

3. Arquitectura y decisiones tecnicas de backoffice:
   - `docs/backoffice/`

4. Indice maestro:
   - `docs/README.md`

## Modelo de ownership

- Owner de contenido backoffice interno: equipo backoffice (support + billing lead).
- Owner de contenido cliente: producto/soporte funcional.
- Owner tecnico transversal: backend lead para exactitud de rutas, estados y permisos.

## Politica de actualizacion

- Todo cambio funcional relevante debe actualizar docs en el mismo PR.
- Se considera "cambio relevante":
  - nuevos estados de lifecycle
  - cambios de permisos por rol
  - nuevas rutas de accion administrativa
  - cambios de mensajes de error operativos
- Si no aplica documentacion, dejar justificacion breve en descripcion del PR.

## Roadmap sugerido

### Fase 1 (inmediata)
- Completar `docs/backoffice/usuario/impersonate.md`.
- Completar `docs/backoffice/usuario/clinicas-core.md`.
- Crear `docs/cliente/inicio-rapido.md`.

### Fase 2
- Crear guias de billing y estados de suscripcion para cliente.
- Consolidar FAQ de soporte repetitivo.

### Fase 3
- Agregar versionado documental simple (fecha + changelog por documento).
- Definir checklist de QA documental previo a release.

## Convenciones de formato

- Titulos cortos y orientados a tarea.
- Secciones minimas:
  - para que sirve
  - quien puede ejecutarlo
  - pasos
  - resultado esperado
  - errores frecuentes
- Evitar exponer secretos o datos sensibles.