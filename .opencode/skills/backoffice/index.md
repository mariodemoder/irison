# Backoffice Skill

## Arquitectura

- Backoffice corre en dominio/subdominio separado (`BACKOFFICE_DOMAIN` env) o prefijo `/backoffice` en local.
- Guardia `auth:admin` — usa el provider `admins` que autentica contra `admin_users` (modelo `App\Models\AdminUser`), NO contra `users`.
- Rutas definidas en `routes/web.php` dentro del grupo `backoffice.` prefix/domain.

## ⚠️ Peligro: Confusión entre `users` y `admin_users`

Los backoffice admins viven en la tabla `admin_users` con modelo `AdminUser`, mientras los usuarios de clínica viven en `users` con modelo `User`. Son tablas completamente independientes con sus propias secuencias de ID.

### Donde morder

Cualquier FK que referencie a un admin backoffice debe apuntar a `admin_users`, NO a `users`.

### Caso real: `subscription_requests.reviewed_by`

La tabla `subscription_requests` tiene dos FKs a usuarios:

| Columna | Apunta a | Quién | Correcto |
|---------|----------|-------|----------|
| `requested_by` | `users.id` | Usuario de clínica que solicita el upgrade | ✅ |
| `reviewed_by` | ~~`users.id`~~ → **`admin_users.id`** | Admin backoffice que aprueba/rechaza | ❌→✅ arreglado |

**El error:** Originalmente `reviewed_by` apuntaba a `users.id` (migración `2026_06_29_131735_create_subscription_requests_table.php:19`), pero en `SubscriptionRequestController.php` (backoffice) se setea con `$request->user('admin')->id`, que es un `admin_users.id`. Esto causaba violación de FK `23503`.

**La solución:**
- Migración `2026_07_02_000000_update_subscription_requests_reviewed_by_foreign.php`: drop FK y recrear apuntando a `admin_users`.
- Modelo `SubscriptionRequest::reviewer()` ahora usa `belongsTo(AdminUser::class, 'reviewed_by')`.

### Regla general

- Si el backoffice escribe un `*_by` (reviewed_by, approved_by, etc.) usando el guard `admin` → la FK debe apuntar a `admin_users`.
- Si el dato viene de un usuario de clínica (guard `web`) → la FK apunta a `users`.
- Nunca asumas que "usuario" siempre significa `users`; verifica el guard que lo crea.
- `AdminUser` y `User` son modelos distintos en tablas distintas. `AdminUser::class` existe en `App\Models\AdminUser.php`, tabla `admin_users`.

## Tenant Management

- ...
