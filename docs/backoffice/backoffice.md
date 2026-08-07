# Backoffice - Lógica Operativa

## Propósito

`backoffice.irison.com` es el panel interno para operación SaaS multi-tenant, separado del uso diario de la clínica.

Este panel centraliza:
- gestión de clínicas (tenants)
- control de suscripciones y lifecycle
- monitoreo de trial / past_due / cancelaciones
- soporte operativo interno
- base de métricas comerciales

## Alcance Implementado (ETAPA 1)

### Identidad interna
- Tabla: `admin_users`
- Guard: `admin` (sesión)
- Provider: `admins` (`App\Models\AdminUser`)
- Login separado para staff en backoffice.

### Roles fijos
- `super_admin`: acceso total a módulos internos.
- `support`: soporte y operaciones de cliente.
- `billing`: operaciones de pagos/facturación.
- `readonly`: solo lectura.

### Seguridad base
- Middleware `auth:admin` para proteger rutas internas.
- Middleware `admin.active` para bloquear cuentas inactivas.
- Middleware `admin.role` para autorización por módulo.
- Logging de autenticación interna:
  - `auth.admin.login.success`
  - `auth.admin.login.failed`

### Operación inicial
- CRUD básico de `admin_users`:
  - alta
  - edición
  - activar/desactivar
  - cambio de contraseña

### Dashboard operativo inicial
Métricas de lectura rápida:
- clínicas totales
- clínicas activas
- trials por vencer (7 días)
- clínicas `past_due`
- clínicas canceladas en período de gracia
- admins internos totales y activos

### Última modificación (2026-05-28): seguridad de tests
- Los tests de Backoffice no pueden ejecutarse fuera de entorno de testing aislado.
- Se exige validación explícita de:
  - `APP_ENV=testing`
  - conexión `sqlite`
  - base `:memory:`
- Si la validación falla, los tests abortan con excepción para evitar cualquier riesgo sobre usuarios reales.
- Se mantiene `RefreshDatabase` únicamente en este contexto aislado para construir esquema temporal sin tocar datos persistentes.

## Alcance Implementado (ETAPA 2)

### Módulo Clínicas (Core)
- Rutas internas de clínicas en backoffice:
  - listado
  - detalle
  - edición
- Filtros operativos por:
  - búsqueda (`name`, `slug`, `email`)
  - estado SaaS (`trial`, `active`, `past_due`, `cancelled`, `suspended`, `expired`)
  - plan (`basic`, `pro`, `enterprise`)

### Entidad tenant (sobre `clinics`)
- Se mantiene `clinics` como entidad real de tenant.
- Campos core añadidos para operación backoffice:
  - `slug`
  - `plan`
  - `status`
  - `stripe_customer_id`
  - `suspended_at`
- Se mantiene compatibilidad con lifecycle previo basado en `subscription_status`.

### Acciones administrativas implementadas
- ver clínica
- editar datos base de tenant
- extender trial
- suspender
- reactivar
- cancelar suscripción (gracia)
- cambiar plan
- ver actividad de clínica

### Login como clínica (impersonate)
- Enfoque implementado: custom seguro (sin paquete), por arquitectura dual guard (`admin` session + `api` sanctum).
- Flujo:
  - `super_admin` inicia impersonate sobre owner de clínica
  - se genera token Sanctum del owner
  - se guarda contexto de impersonación en sesión backoffice
  - redirección a frontend con token temporal
- Cierre seguro:
  - stop impersonate revoca token
  - logout de admin también revoca token y limpia contexto

### Auditoría operativa ETAPA 2
- Tabla: `backoffice_clinic_activities`.
- Eventos auditados (mínimo):
  - `clinic.updated`
  - `clinic.trial.extended`
  - `clinic.suspended`
  - `clinic.reactivated`
  - `clinic.subscription.canceled`
  - `clinic.plan.changed`
  - `admin_impersonate.start`
  - `admin_impersonate.end`
- Contexto estable por evento:
  - `clinic_id`
  - `admin_user_id`
  - `target_user_id` (si aplica)
  - `result`
  - `context` (metadatos no sensibles)

### Matriz de permisos aplicada en rutas
- `super_admin`: acceso total + impersonate.
- `support`: ver/editar/extender/suspender/reactivar clínicas.
- `billing`: ver + cancelar suscripción + cambiar plan.
- `readonly`: solo lectura (listado/detalle).

### Pruebas ETAPA 2
- Suite feature dedicada al módulo clínicas:
  - permisos por rol
  - suspensión/reactivación
  - cancelación/cambio de plan
  - inicio de impersonate

## Convivencia con el producto principal

No se modifica el flujo de la app de clínicas (`app.irison.com`):
- API tenant con `auth:sanctum` se mantiene intacta.
- Middleware de aislamiento `clinic_id` se conserva.
- Webhooks públicos de billing/Stripe continúan fuera del backoffice.

## Rutas de Backoffice

Si `BACKOFFICE_DOMAIN` está configurado, el panel vive en ese subdominio.

Si no está configurado (local/dev), usa prefijo:
- `/backoffice/login`
- `/backoffice/dashboard`
- `/backoffice/admin-users`

## Próximas Etapas

### ETAPA 2 (sugerida)
- módulo de clínicas: listado + detalle operativo + filtros lifecycle
- suspensión/reactivación de cuenta tenant
- intervención de billing (reintentos, notas operativas)
- timeline de eventos por clínica

### ETAPA 3 (sugerida)
- métricas comerciales avanzadas
- reportes de facturación exportables
- automatizaciones para reducir soporte manual

## Diseño ETAPA 3 (Trials): Matriz Operativa

Duración estimada: 3-4 días.

### Objetivo funcional
- Registro crea trial de 30 días.
- Journey automático por hitos de día 1, 7, 20, 27 y 30.
- Conversión por pago en cualquier punto del trial.
- Día 30 activa suspensión parcial (solo lectura, sin transacciones).
- Al final de la ventana de gracia sin conversión, marcar churn.

### Estados operativos de trial
- `trial`: trial vigente.
- `trial_warning`: trial vigente con alertas de vencimiento enviadas.
- `trial_read_only`: trial vencido con acceso parcial.
- `active`: clínica convertida por pago.
- `churned`: trial/gracia finalizados sin conversión.

### Matriz evento x estado x acción

| Evento automático | Estado previo esperado | Acción principal | Estado resultante | Notas |
|---|---|---|---|---|
| Registro | N/A | Crear trial 30 días, registrar baseline de journey | `trial` | `trial_ends_at = now + 30 días` |
| Día 1 bienvenida | `trial` | Enviar email bienvenida | `trial` | idempotente: no reenviar |
| Día 7 onboarding | `trial` | Enviar tips onboarding | `trial` | idempotente: no reenviar |
| Día 20 aviso | `trial` | Enviar aviso "termina pronto" | `trial_warning` | mover a warning si no estaba |
| Día 27 CTA fuerte | `trial` o `trial_warning` | Enviar CTA de conversión | `trial_warning` | último empuje previo vencimiento |
| Conversión pago | `trial` o `trial_warning` o `trial_read_only` | Confirmar pago y activar suscripción | `active` | cancelar eventos trial pendientes |
| Día 30 vencimiento | `trial` o `trial_warning` | Aplicar suspensión parcial | `trial_read_only` | bloquear mutaciones, permitir lectura |
| Fin gracia sin pago | `trial_read_only` | Marcar churn | `churned` | alimentar métricas de churn |

### Reglas de exclusión
- Si estado actual es `active`, no ejecutar eventos de trial.
- Si estado actual es `churned`, no reenviar onboarding/warnings.
- No reenviar un hito ya marcado como enviado.

### Reglas de idempotencia
- Cada hito (día 1/7/20/27/30) se registra con llave única por clínica y tipo de evento.
- Reintentos de job deben ser seguros: si ya existe marca de envío, no duplicar.
- Conversión a `active` anula ejecución posterior de eventos de trial.

### Scheduler (Laravel)
- Runtime: `php artisan schedule:work`.
- Tareas mínimas:
  - detectar trials vencidos
  - enviar emails por hito
  - suspender acceso (read-only)
  - marcar churn

Frecuencia sugerida:
- evaluación de hitos: cada 30 minutos.
- sincronización de suspensión/read-only: cada hora.
- marcación de churn: diario (madrugada).

### Contrato de auditoría ETAPA 3
Eventos recomendados:
- `trial.started`
- `trial.email.day1_sent`
- `trial.email.day7_sent`
- `trial.email.day20_sent`
- `trial.email.day27_sent`
- `trial.read_only_activated`
- `trial.converted`
- `trial.churned`

Contexto mínimo por evento:
- `event`
- `result`
- `clinic_id`
- `user_id` (si aplica)
- `error_code` (si falla)

### Criterios de aceptación
- El journey se ejecuta completo y sin duplicados para una clínica de prueba.
- La conversión interrumpe correctamente eventos pendientes de trial.
- Día 30 bloquea transacciones y mantiene lectura.
- El churn se marca solo tras expirar trial y gracia sin pago.
- Cobertura de pruebas específicas delegada a QA.

## ETAPA 3 — Ejecución planificada (4 días)

### Día 1 — Base de datos, modelo y correo base
- Entregar migración `trial_journey_events` con unicidad por `clinic_id + event_key`.
- Implementar `App\Models\TrialJourneyEvent` (casts, fillable, relación con clínica).
- Crear mailable `App\Mail\TrialLifecycleMail` y vista `resources/views/emails/trial-lifecycle.blade.php`.
- Definir copy inicial para hitos d1, d7, d20, d27, d30.

### Día 2 — Motor lifecycle + comando + scheduler
- Implementar `App\Services\Trials\TrialLifecycleService`:
  - Detección de hitos por antigüedad del tenant (día 1/7/20/27/30).
  - Idempotencia vía `trial_journey_events`.
  - Transición a `trial_warning` en hitos d20/d27.
  - Activación de `trial_read_only` al vencer trial.
  - Marcado `churned` tras ventana de gracia.
- Crear comando `trials:process` (`App\Console\Commands\ProcessTrialLifecycle`).
- Programar scheduler en `bootstrap/app.php` cada 30 minutos.

### Día 3 — Integración de acceso y estado API
- Alinear middleware `CheckSubscriptionAccess` para:
  - reconocer `trial_read_only` como solo lectura;
  - bloquear `churned` con código explícito.
- Ajustar `/api/me` (`Api\MeController`) para exponer estado operativo coherente:
  - `trial`, `trial_read_only`, `active`, `blocked`, `TRIAL_CHURNED`.
- Alinear creación/activación de clínicas para iniciar en estado `trial` operativo.

### Día 4 — Hardening y testing
- Ejecutar suite focalizada de ETAPA 3 (milestones/transiciones/idempotencia).
- Verificar no-regresión en backoffice core (auth, roles, clinics).
- Validar scheduler y comando manual en entorno local.
- Preparar checklist de despliegue:
  - migraciones aplicadas,
  - scheduler activo,
  - monitoreo de logs de lifecycle.

## Plan de testing ETAPA 3

Cobertura mínima obligatoria:
- Milestones automáticos d1/d7/d20/d27/d30.
- Idempotencia: doble ejecución no duplica eventos.
- Transición a `trial_read_only` al vencer trial.
- Transición a `churned` después de gracia sin pago.
- Exclusión de envíos en clínicas `active` o `churned`.

Pruebas implementadas en:
- `tests/Feature/Trials/TrialLifecycleTest.php`

Regla de seguridad de pruebas:
- `APP_ENV=testing`
- `database.default=sqlite`
- `database.connections.sqlite.database=:memory:`

Delegación QA:
- Para regresión amplia, contratos HTTP y validación de escenarios de riesgo, delegar ejecución especializada a `QA` (`.github/agents/qa.agent.md`).

## Última modificación (2026-06-02): ETAPA 3 Fase 1 (Hardening)

- Se parametrizó la gracia de trial en `modules/Subscriptions/Config/billing.php` (`trial_grace_days`, default 7).
- Se añadió columna `clinics.churned_at` para trazabilidad operativa del churn.
- `TrialLifecycleService` ahora:
  - actualiza `status=trial_warning` junto a `subscription_status` en hitos d20/d27,
  - marca `churned_at` al pasar a `churned`,
  - usa gracia configurable (sin hardcode de 7 días),
  - registra warning estructurado si no hay email válido para un hito.
- `Clinic` usa gracia configurable para ventana de read-only de trial.
- Cobertura actualizada en `tests/Feature/Trials/TrialLifecycleTest.php` para validar:
  - transición de `status` a `trial_warning`,
  - presencia de `churned_at` al marcar churn.
