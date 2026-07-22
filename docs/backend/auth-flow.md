# Auth Flow — Registro, Activación, Login y Password Reset

## Resumen

Flujo completo de autenticación de usuarios SPA (Vue 3 + Laravel API).

## Modelos involucrados

- `User` — campo clave: `email_verified_at` (nullable timestamp)
  - `null` = cuenta no activada
  - con valor = cuenta activada
- `Clinic` — campo `isSuspended()` se verifica después de la activación

## 1. Registro

**Ruta:** `POST /api/register`
**Controller:** `app/Http/Controllers/Api/RegisterController.php`

1. Crea usuario con `email_verified_at = null`
2. Genera URL de activación firmada (expira en 24h)
3. Envía `AccountActivationMail` con link de activación

## 2. Activación de cuenta

**Ruta:** `GET /api/register/activate/{user}` (firmada + throttle:6,1)
**Controller:** `app/Http/Controllers/Api/ActivateAccountController.php`

1. Valida firma del link
2. Si `email_verified_at` es null → lo setea a `now()`
3. Inicia trial de 30 días para la clínica
4. Redirige a `/login?activation=success`

## 3. Login

**Ruta:** `POST /api/login`
**Controller:** `app/Http/Controllers/Api/AuthController.php` (línea 21)

**Checks en orden:**
1. Credenciales válidas (email + password hash)
2. `email_verified_at` no es null → si es null: **403** "Debes activar tu cuenta desde el correo antes de iniciar sesión."
3. Clínica no suspendida → si suspendida: **403** "Por el momento tu cuenta está suspendida."
4. Si todo OK: crea token Sanctum, actualiza `last_login_at`

## 4. Password Reset (Forgot + Reset)

### 4a. Solicitar reset

**Ruta:** `POST /api/password/forgot`
**Controller:** `app/Http/Controllers/Api/Auth/PasswordRecoveryController.php` (línea 25)

1. Rate limit: max 4 emails por email (`PasswordRecoveryLimiter`)
2. `Password::sendResetLink()` genera token en `password_reset_tokens`
3. Envía `ResetPasswordNotificationEs` (email en español)
4. Respuesta neutral: "Si el email existe, te hemos enviado instrucciones..."

### 4b. Restablecer contraseña

**Ruta:** `POST /api/password/reset`
**Controller:** `app/Http/Controllers/Api/Auth/PasswordRecoveryController.php` (línea 54)

1. Valida token + email + password (confirmado)
2. `Password::reset()` ejecuta callback que:
   - Hashea la nueva contraseña
   - Genera nuevo `remember_token`
   - **Si `email_verified_at` es null → lo setea a `now()`** (auto-activación)
   - Guarda con `forceFill()`
3. Dispara evento `PasswordReset`
4. Respuesta: "Contraseña actualizada correctamente. Ya puedes iniciar sesión."

**Nota importante:** El reset de contraseña auto-activa la cuenta porque el usuario demostró propiedad del email al recibir y usar el link de reset. Esto evita el deadlock donde un usuario puede restablecer su contraseña pero no puede loguearse.

### 4c. Frontend

- `ForgotPassword.vue` → envía `POST /api/password/forgot`
- `ResetPassword.vue` → envía `POST /api/password/reset`, redirige a `/login?email=...`
- URL de reset generada en `AppServiceProvider.php` (línea 35-41)

## 5. Cambio de contraseña (usuario logueado)

**Ruta:** `POST /me/password`
**Controller:** `app/Http/Controllers/Api/ProfilePasswordController.php`

Requiere autenticación. Cambia password sin necesidad de token de reset.

## Archivos clave

| Propósito | Archivo |
|-----------|---------|
| Login API | `app/Http/Controllers/Api/AuthController.php:21` |
| Check de activación | `app/Http/Controllers/Api/AuthController.php:47` |
| Registro | `app/Http/Controllers/Api/RegisterController.php` |
| Activación | `app/Http/Controllers/Api/ActivateAccountController.php` |
| Password reset API | `app/Http/Controllers/Api/Auth/PasswordRecoveryController.php` |
| Password reset web | `app/Http/Controllers/Auth/NewPasswordController.php` |
| Rate limiter | `app/Services/Auth/PasswordRecoveryLimiter.php` |
| Notificación reset | `app/Notifications/ResetPasswordNotificationEs.php` |
| URL de reset | `app/Providers/AppServiceProvider.php:35` |
| User model | `app/Models/User.php` |
| Auth config | `config/auth.php` |
| Rutas API | `routes/api.php:45-49` |
| Rutas web auth | `routes/auth.php` |
