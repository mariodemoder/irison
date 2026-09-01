---
description: Mejora una petición del usuario (mejores prácticas de prompting) para que el agente Plan la interprete con eficacia, y continúa planificando tras aprobación.
agent: plan
---

# promp — Refinar petición para el agente Plan

Refina una petición del usuario aplicando las mejores prácticas de prompting (artículo oficial de Claude: https://platform.claude.com/docs/es/build-with-claude/prompt-engineering/claude-prompting-best-practices) para que el agente Plan la interprete sin ambigüedad. Tras la aprobación del prompt mejorado, continúa con el flujo normal de planificación.

## Proceso

1. **Contexto**: lee `AGENTS.md` y carga la skill `core` (o la del dominio vía la herramienta `skill`) para conocer las convenciones del proyecto (aislamiento tenancy, capas Controllers → Services → Policies, webhooks públicos, reminder scheduling, docs-after-approval, clean/QA solo a demanda, cambios irreversibles de billing/tenancy requieren confirmación).

2. **Análisis**: la petición del usuario llega como `$ARGUMENTS`. Descompónla en:
   - **Objetivo**: qué pide y para qué.
   - **Contexto**: situación actual y el "por qué" importa.
   - **Alcance**: qué incluye y qué queda explícitamente fuera.
   - **Riesgos**: billing/facturación, aislamiento tenancy, webhooks públicos, reminder scheduling, operaciones destructivas.
   - **Criterios de aceptación**: cómo se sabrá que está hecho.
   - **Entregables**: formato de salida esperado.
   - **Ambigüedades y supuestos**: qué no está claro o se asume.

3. **Preguntas**: si hay ambigüedades críticas, usa la herramienta `question` para resolverlas ANTES de redactar el prompt final. Ambigüedades menores → sección `<open_questions>`.

4. **Redacta el prompt mejorado** siguiendo las mejores prácticas:
   - **Rol explícito**: asigna el rol de "agente Plan de Irison" (orquestador que planifica, descompone, delega en build y valida).
   - **Estructura XML**: organiza el prompt con etiquetas:
     - `<objective>` — objetivo claro y directo (qué + para qué)
     - `<context>` — por qué, situación actual, convenciones relevantes del proyecto
     - `<scope>` — incluye/excluye explícitamente
     - `<constraints>` — restricciones no negociables (tenancy, capas, webhooks públicos, recordatorios)
     - `<deliverables>` — salida esperada y su formato
     - `<acceptance_criteria>` — definición de hecho
     - `<open_questions>` — dudas menores a resolver antes de ejecutar
   - **Claro y directo**: elimina verborrea, usa verbos concretos, evita ambigüedad.
   - **Ejemplos**: incluye un ejemplo solo si aporta claridad al formato esperado.
   - **Anti-alucinaciones**: el prompt debe pedir al Plan investigar los archivos antes de afirmar, no inventar código, evitar sobre-ingeniería y no hardcodear valores.

5. **Aprobación**: presenta el prompt mejorado en un bloque de código y resume en 2-3 líneas qué cambiaste. Pide al usuario aprobarlo o pedir ajustes.
   - **Aprobado** → continúa con el flujo normal de planificación del agente Plan (carga la skill del dominio vía la herramienta `skill` — ver Skill Index en `AGENTS.md` — y presenta el plan para aprobación).
   - **Rechazado / ajustes** → itera sobre el prompt hasta que el usuario lo apruebe.

## Reglas

- Mientras refinas el prompt, no edites archivos ni ejecutes nada que no sea lectura/análisis.
- Mantén el idioma del usuario (español en este proyecto).
- No inventes detalles que el usuario no haya dado: si falta información crítica, pregunta en lugar de asumir.
