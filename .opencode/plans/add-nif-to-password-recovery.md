# Plan: Agregar NIF a recuperación de contraseña

## Archivos a modificar (3)

### 1. `app/Http/Controllers/Api/Auth/PasswordRecoveryController.php`

**Cambios:**
- Añadir `use App\Rules\ValidateNIFFormat;` en imports
- En `sendResetLink()`, agregar `'nif' => ['required', 'string', new ValidateNIFFormat]` a la validación
- Después de rate limiting, buscar usuario por email y verificar NIF de la clínica:

```php
$user = User::where('email', $email)->first();

if ($user) {
    $clinic = $user->clinic;

    if ($clinic && strtoupper(trim($clinic->nif)) === strtoupper(trim($data['nif']))) {
        Password::sendResetLink(['email' => $email]);
        $this->passwordRecoveryLimiter->markSent($email);
    }
}

return response()->json([
    'message' => 'Si los datos son correctos, te hemos enviado instrucciones para recuperar tu contraseña.',
]);
```

### 2. `resources/js/views/ForgotPassword.vue`

**Cambios en `<script>`:**
- Cambiar `import { ref }` a `import { ref, computed }`
- Agregar `const nif = ref('')`
- Agregar computed `nifError` (misma lógica que Register.vue)
- En `submit()`, enviar `nif: nif.value.trim().toUpperCase()`

**Cambios en `<template>`:**
- Agregar campo NIF/NIE después del email:
```html
<BaseInput v-model="nif" label="NIF/NIE" placeholder="12345678Z"
           @input="nif = $event.target.value.toUpperCase()" />
<p v-if="nifError" class="input-error">{{ nifError }}</p>
```

### 3. `tests/Feature/Api/Auth/PasswordRecoveryApiTest.php`

**Cambios en `createUser()`:**
- Agregar `'nif' => '12345678Z'` al `Clinic::create()` array

**Actualizar tests existentes:**
- `test_it_returns_neutral_message_and_sends_email_for_existing_user`: agregar `'nif' => '12345678Z'`
- `test_reset_email_uses_spanish_subject`: agregar `'nif' => '12345678Z'`
- `test_it_returns_neutral_message_for_unknown_email_without_leaking_existence`: agregar `'nif' => '12345678Z'`

**Nuevos tests:**
- `test_it_rejects_wrong_nif_without_sending_email`: NIF incorrecto → 200 pero no envía notificación
- `test_it_requires_nif_field`: sin NIF → 422

## Comandos de verificación

```bash
php artisan test tests/Feature/Api/Auth/PasswordRecoveryApiTest.php
```
