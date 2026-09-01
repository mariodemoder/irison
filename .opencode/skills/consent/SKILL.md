---
name: consent
description: Use when working on consentimientos informados: ConsentTemplate, PatientConsent, categories, ConsentVariableResolver {variables}, ConsentPdfGenerator (Browsershot, SHA-256), ConsentSignatureService (token 72h), SignPad, remote sign flows, public sign routes.
---

# Skill: Consentimientos Informados

## Cuándo usar este skill

Cuando el usuario pregunte o reporte problemas sobre plantillas de consentimiento, firma digital, envío de enlaces de firma, generación de PDF, o el flujo completo de consentimientos informados.

## Arquitectura

Backend (Laravel 12 API):

- `app/Models/ConsentTemplate.php` — Plantilla con contenido HTML + variables `{...}`
- `app/Models/PatientConsent.php` — Documento generado con status (pending/sent/signed/rejected/revoked)
- `app/Models/ConsentCategory.php` — Categorías para agrupar plantillas
- `app/Models/ConsentLog.php` — Log de eventos del consentimiento
- `app/Services/Consents/ConsentVariableResolver.php` — Reemplaza `{variable}` por datos reales + guarda snapshot
- `app/Services/Consents/ConsentPdfGenerator.php` — Genera PDF con Browsershot en memoria (nunca en disco), SHA-256
- `app/Services/Consents/ConsentSignatureService.php` — Token UUID + caducidad 72h + firma
- `app/Http/Controllers/Api/ConsentTemplateController.php` — CRUD plantillas
- `app/Http/Controllers/Api/PatientConsentController.php` — CRUD + send/sign/download/revoke/resend
- `app/Http/Controllers/Api/ConsentSignController.php` — Público: show + sign por token
- `routes/api.php` — 12 rutas protegidas + 3 públicas (prefijo `consent/sign/{token}`)

Frontend (Vue 3):

- `resources/js/views/consents/ConsentTemplatesIndex.vue` — Listado + botón ayuda
- `resources/js/views/consents/ConsentTemplatesForm.vue` — Crear/editar plantilla
- `resources/js/views/consents/ConsentSignPublic.vue` — Página pública de firma remota
- `resources/js/components/consents/SignPad.vue` — Pad de firma sobre canvas
- `resources/js/components/consents/HelpModal.vue` — Modal de ayuda con casos de uso
- `resources/js/views/consents/PatientConsents.vue` — Embed en ficha paciente

## Casos de uso

### Caso 1 — Administrador crea plantilla

1. Navega a **Consentimientos** en el sidebar
2. Pulsa **Nueva plantilla**
3. Escribe título, contenido HTML con variables `{paciente_nombre}`, `{clinica}`, etc.
4. Guarda → `POST /api/consent-templates`

### Caso 2 — Recepción genera consentimiento

1. Abre ficha del paciente (`/patients/{id}`)
2. En la card **Consentimientos** pulsa **Nuevo**
3. Selecciona plantilla → `POST /api/patients/{patient}/consents` con `template_id`
4. Irison llama a `ConsentVariableResolver::resolve()` que reemplaza `{paciente_nombre}` → "Juan", etc.
   y guarda `content_html` (HTML final) + `snapshot` (JSON con valores originales)
5. Se genera hash SHA-256 de integridad

### Caso 3 — Firma presencial en clínica

1. Recepción pulsa **Firmar** en el consentimiento pendiente
2. Aparece el `SignPad` (canvas). El paciente firma con el dedo
3. Confirmar → `POST /api/consents/{consent}/sign` con `signature_svg`
4. Status cambia a `signed`, se guarda `signed_at`, se recalcula hash
5. Se dispara evento `ConsentSigned` → log en `consent_logs`
6. PDF disponible para descargar en **PDF**

### Caso 4 — Firma remota (online)

1. Recepción pulsa **Enviar** → `POST /api/consents/{consent}/send`
2. `ConsentSignatureService::generateToken()` genera UUID + expires_at (72h)
3. Status → `sent`. Se dispara `ConsentSent`
4. `SendConsentEmail` envía email con enlace `{frontend_url}/sign/{token}`
5. Paciente abre enlace → `GET /api/consent/sign/{token}` (público, sin auth)
   - Si OK: se muestra contenido del consentimiento + SignPad
   - Si expirado: 410 Gone
   - Si ya firmado: 410 con `status: already_signed`
6. Paciente firma → `POST /api/consent/sign/{token}` (público)
7. Misma lógica que Caso 3: status → `signed`, hash, log, evento

## Variables disponibles

| Variable | Origen |
|---|---|
| `{paciente_nombre}` | `$patient->first_name` |
| `{paciente_apellidos}` | `$patient->last_name` |
| `{dni}` | `$patient->nif` |
| `{telefono}` | `$patient->phone` |
| `{email}` | `$patient->email` |
| `{fecha}` | `now()->format('d/m/Y')` |
| `{profesional}` | `$user->name` (quien crea) |
| `{clinica}` | `$clinic->name` |
| `{tratamiento}` | `''` (pendiente) |
| `{especialidad}` | `$user->profile->name` |

## Rutas API clave

### Protegidas (auth:sanctum + clinic + check.subscription)

| Método | Ruta | Controlador |
|---|---|---|
| GET | `/api/consent-categories` | ConsentCategoryController@index |
| POST | `/api/consent-categories` | ConsentCategoryController@store |
| PUT | `/api/consent-categories/{id}` | ConsentCategoryController@update |
| DELETE | `/api/consent-categories/{id}` | ConsentCategoryController@destroy |
| GET | `/api/consent-templates` | ConsentTemplateController@index |
| POST | `/api/consent-templates` | ConsentTemplateController@store |
| GET | `/api/consent-templates/{id}` | ConsentTemplateController@show |
| PUT | `/api/consent-templates/{id}` | ConsentTemplateController@update |
| DELETE | `/api/consent-templates/{id}` | ConsentTemplateController@destroy |
| GET | `/api/consent-templates/{id}/versions` | ConsentTemplateController@versions |
| GET | `/api/patients/{patient}/consents` | PatientConsentController@index |
| POST | `/api/patients/{patient}/consents` | PatientConsentController@store |
| GET | `/api/consents/{consent}` | PatientConsentController@show |
| POST | `/api/consents/{consent}/send` | PatientConsentController@send |
| POST | `/api/consents/{consent}/resend` | PatientConsentController@resend |
| POST | `/api/consents/{consent}/sign` | PatientConsentController@signPresential |
| GET | `/api/consents/{consent}/download` | PatientConsentController@download |
| POST | `/api/consents/{consent}/revoke` | PatientConsentController@revoke |

### Públicas (throttle:30,1, sin auth)

| Método | Ruta | Controlador |
|---|---|---|
| GET | `/api/consent/sign/{token}` | ConsentSignController@show |
| POST | `/api/consent/sign/{token}` | ConsentSignController@sign |

## Eventos

| Evento | Listeners |
|---|---|
| `ConsentCreated` | `LogConsentActivity@handleCreated` |
| `ConsentSent` | `LogConsentActivity@handleSent`, `SendConsentEmail@handle` |
| `ConsentSigned` | `LogConsentActivity@handleSigned` |
| `ConsentRevoked` | `LogConsentActivity@handleRevoked` |

## Políticas

- `ConsentCategoryPolicy`: viewAny/view → todos clinic; create/update/delete → hasFullAccess
- `ConsentTemplatePolicy`: viewAny/view → todos clinic; create/update/delete → hasFullAccess
- `PatientConsentPolicy`: viewAny/signPresential → todos clinic; create/update/delete → hasFullAccess

## PatientConsents Embed Layout

`resources/js/views/consents/PatientConsents.vue` — Embed en ficha paciente (dentro del history-grid 2 columnas).

### Layout
- **Una línea por consentimiento** (flex row): título + fecha + badge + acciones.
- Título con `ellipsis` y `max-width: 180px`, `white-space: nowrap`.
- Badge de estado con colores: pending (amarillo), sent (azul), signed (verde), revoked (gris), rejected (rojo).
- Acciones nowrap sin wrap: Enviar, Firmar, Reenviar, PDF (solo signed), Revocar (solo signed).
- Estilos clave: `.consent-info { flex-shrink:1; min-width:0 }`, `.consent-actions { flex-wrap:nowrap; flex-shrink:0 }`.

### Modales
- **Nuevo consentimiento**: modal backdrop con selector de plantilla (`/api/consent-templates`).
- **Firmar consentimiento**: modal con `<SignPad>` y confirmación vía `POST /api/consents/{consent}/sign`.

## Consideraciones importantes

1. **PDF nunca se almacena en disco**: se genera en memoria con Browsershot bajo demanda
2. **Hash SHA-256**: se calcula al crear y al firmar; si el contenido cambió, se rechaza la descarga (409)
3. **Token UUID**: expira a las 72h; rutas públicas sin auth pero con throttle
4. **Snapshot**: los valores de las variables se congelan al crear el consentimiento
5. **Sintaxis `{variable}`**: se usó `{...}` en lugar de `{{...}}` para evitar conflicto con Blade
6. **Rutas públicas de firma**: fuera del middleware `auth:sanctum` para que pacientes sin cuenta puedan firmar
