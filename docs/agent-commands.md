# Comandos de agentes (opencode)

Comandos opencode definidos para el flujo de agentes de Irison. Se invocan con el prefijo `con` (ej. `con promp`).

## `con promp` — Refinar petición para el agente Plan

Mejora una petición del usuario aplicando mejores prácticas de prompting (artículo oficial de Claude: https://platform.claude.com/docs/es/build-with-claude/prompt-engineering/claude-prompting-best-practices) para que el agente Plan la interprete con eficacia.

**Flujo:**
1. Escribes la petición tal como la tienes en mente: `con promp <tu petición>`.
2. El comando (ejecutado en el agente `plan`) lee `AGENTS.md` y el índice de skills, y analiza la petición: objetivo, contexto, alcance, riesgos (billing, tenancy, webhooks, recordatorios) y criterios de aceptación.
3. Si hay ambigüedades críticas, pregunta antes de redactar; las menores quedan como preguntas abiertas.
4. Produce el prompt mejorado estructurado con etiquetas XML (`<objective>`, `<context>`, `<scope>`, `<constraints>`, `<deliverables>`, `<acceptance_criteria>`, `<open_questions>`).
5. Lo presenta para aprobación:
   - **Aprobado** → continúa con la planificación normal del agente Plan.
   - **Rechazado** → itera hasta que el usuario lo apruebe.

**Definición**: `.opencode/command/promp.md` (`agent: plan`).

> Nota: tras crear o modificar comandos hay que **reiniciar opencode** — la configuración se carga al arrancar, no en caliente.

## Invocaciones a demanda (referencia en AGENTS.md)

- `con clean` — dead-code cleanup. Solo bajo demanda; nunca borra tests.
- `con tests` — QA, tests, regresión o validación. Solo bajo demanda.

> Estas invocaciones son conceptuales (referidas en AGENTS.md en la Cleaning/Verification Layer); no tienen archivo de comando definido.
