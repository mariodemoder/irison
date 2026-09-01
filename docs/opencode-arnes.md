# Arnés opencode de Irison

Cómo está organizado el harness de agentes/skills/comandos de este repo, y cómo se cargan los skills.

## Estructura

```
C:\wamp64\www\dueleahi\
├── AGENTS.md                  ← Reglas globales: operating model, routing matrix, convenciones no negociables, clean/QA rules
├── .opencode\
│   ├── agents\                ← plan.md (orchestrator) y build.md (executor) — frontmatter + system prompt
│   ├── command\promp.md       ← comando `con promp` (refinar petición para el agente Plan)
│   ├── skills\                ← Skills de DOMINIO del proyecto (formato opencode nativo)
│   │   ├── index.md           ← Router humano (referencia, no necesario para los agentes)
│   │   ├── core\SKILL.md, backend\SKILL.md, frontend\SKILL.md, auth\SKILL.md, billing\SKILL.md,
│   │   │   appointments\SKILL.md, bonus\SKILL.md, booking\SKILL.md, consent\SKILL.md,
│   │   │   activity\SKILL.md, deployment\SKILL.md, company-services\SKILL.md, qa\SKILL.md,
│   │   │   team\SKILL.md, backoffice\SKILL.md, patient-portal\SKILL.md
│   │   └── (sub-recursos: backoffice/upgrade-flow.md, backoffice/hard-delete.md, frontend/menu-routing.md)
│   └── plans\                 ← (vacío; reservado)
├── .agents\skills\            ← Skills vendored/terceros (frontend): vue-best-practices, vite-patterns, frontend-a11y, ui-to-vue
└── docs\                      ← Documentación oficial del proyecto (backend, backoffice, qa, deployment, cliente)
```

## Mecanismo de descubrimiento de skills

opencode escanea `**/SKILL.md` dentro de los directorios de skills y expone cada skill al modelo con su `name` + `description` (frontmatter). El modelo los carga **bajo demanda** con la herramienta `skill`.

- `.opencode/skills/<name>/SKILL.md` — skills de dominio/proyecto (ruta por defecto del proyecto).
- `.agents/skills/<name>/SKILL.md` — skills de terceros (external/community), auto-descubiertos igualmente.

Requisitos del frontmatter:

```markdown
---
name: mi-skill          # lowercase hyphen-separated, máx 64 chars
description: Use when ... (qué hace + cuándo usarla; sin description el skill NO se muestra)
---
```

Reglas:

- La `description` es obligatoria y debe front-load las keywords/filenames que disparan la carga (ej. "booking", "Stripe", "bonos", `Form.vue`).
- Los archivos `.md` no `SKILL.md` dentro de una carpeta de skill NO se convierten en skills: quedan como recursos del skill (referencias relativas).
- Tras crear/modificar un skill, un agente, un comando o `opencode.json` hay que **reiniciar opencode** — la config se carga al arrancar, no en caliente.

## Flujo operativo

1. `AGENTS.md` se inyecta completo en cada sesión (contexto raíz).
2. plan/build leen AGENTS.md → cargan la skill del dominio con la herramienta `skill` (sin leer índices manuales).
3. build implementa usando el skill de dominio + las skills vendored que apliquen (`vue-best-practices`, `frontend-a11y`, etc.).
4. Tras cada plan aprobado, `docs/` se actualiza (regla docs-after-approval).

## Mantenimiento

- Nuevo módulo/dominio → crear `.opencode/skills/<nombre>/SKILL.md` con frontmatter + contenido, y añadir una línea al `index.md` + al Skill Index de `AGENTS.md`.
- Cambiar un sub-recurso (ej. `backoffice/upgrade-flow.md`) → actualizar la referencia relativa en su `SKILL.md`.
- No duplicar contenido de `docs/`: los skills pueden referenciar `docs/` con rutas relativas o absolutas de repo.
- `con clean` / `con tests` quedan bajo demanda (no ejecución automática).

## Referencias

- Router humano de skills: `.opencode/skills/index.md` y sección "Skill Index" de `AGENTS.md`.
- Comandos de agentes: `docs/agent-commands.md`.
- Documentación oficial: `docs/README.md`.