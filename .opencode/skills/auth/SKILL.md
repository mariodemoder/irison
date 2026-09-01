---
name: auth
description: Use when working on authentication/authorization: Sanctum, login/register/password reset, profile roles (admin/manager/professional), policies, registration flow, financial data stripping for professional viewers.
---

# Auth / Autenticación Skill

Sistema de autenticación con Laravel Sanctum y control de acceso basado en perfiles (profile-based access control).

## Core Files

### Backend

| Capa | Archivo | Propósito |
|---|---|---|
| Model | `app/Models/User.php` | `isOwner()`, `isAdmin()`, `isManager()`, `isProfessional()`, `hasFullAccess()`, `isViewer()` |
| Model | `app/Models/Profile.php` | Perfiles: admin, manager, professional (seed) |
| Model | `app/Traits/MultiTenantAuthorization.php` | `sameClinic()` helper para policies |
| Policy | `app/Policies/BasePolicy.php` | Base: `view/create/update/delete/restore/forceDelete` requieren `hasFullAccess()` |
| Policy | `app/Policies/AppointmentPolicy.php` | Professionals: solo `viewAny`/`view` (propios). Owner/admin/manager: CRUD completo |
| Policy | `app/Policies/PatientPolicy.php` | Professionals: solo `viewAny`/`view`. Owner/admin/manager: CRUD completo |
| Policy | `app/Policies/PaymentPolicy.php` | Solo `hasFullAccess()` |
| Policy | `app/Policies/ProductPolicy.php` | Solo `hasFullAccess()` |
| Policy | `app/Policies/DocumentPolicy.php` | Solo `hasFullAccess()` |
| Policy | `app/Policies/PackPolicy.php` | Solo `hasFullAccess()` |
| Policy | `app/Policies/ReminderPolicy.php` | Solo `hasFullAccess()` |
| Controller | `app/Http/Controllers/Api/RegisterController.php` | Registro + asignación `profile_id = admin` al owner |
| Controller | `app/Http/Controllers/Api/PatientController.php` | `stripFinancialData()` para profesionales |
| Service | `app/Services/Appointments/AppointmentService.php` | `list()` filtra por `professional_id` si el usuario es viewer |
| Middleware | `app/Http/Middleware/EnsureClinic.php` | Asegura que existe `activeClinic` |
| Middleware | `app/Http/Middleware/CheckSubscriptionAccess.php` | Verifica suscripción activa |

### Frontend

| Archivo | Propósito |
|---|---|
| `resources/js/views/auth/Login.vue` | Login SPA |
| `resources/js/views/auth/ForgotPassword.vue` | Recuperar contraseña |
| `resources/js/views/auth/ResetPassword.vue` | Resetear contraseña |
| `resources/js/layouts/MainLayout.vue` | Sidebar: condicionar navItems según perfil |

## Role System

### Profiles (profile_id → Profile.slug)

Tres perfiles definidos en `profiles` seed:

| Slug | Acceso | Descripción |
|---|---|---|
| `admin` | Full access (`hasFullAccess()`) | Dueño o administrador de la clínica |
| `manager` | Full access (`hasFullAccess()`) | Gestor con acceso completo |
| `professional` | Viewer (`isViewer()`) | Solo agenda propia y datos básicos de pacientes |

### Legacy Role

El User conserva `role` columna (`owner`, `user`). El owner (`role = 'owner'`) siempre tiene perfil `admin` fijo (no modificable desde Team).

```php
// app/Models/User.php — role helpers
$user->isOwner();        // $this->role === 'owner'
$user->isAdmin();        // $this->profile?->slug === 'admin'
$user->isManager();      // $this->profile?->slug === 'manager'
$user->isProfessional(); // $this->profile?->slug === 'professional'
$user->hasFullAccess();  // isOwner() || isAdmin() || isManager()
$user->isViewer();       // isProfessional() (alias semántico)
```

El modelo User define `$attributes = ['role' => 'owner']` como default explícito.

## Policy Layer

### BasePolicy (abstract)

Todas las policies concretas heredan de `BasePolicy`:

```php
view($user, $model)     → sameClinic() && hasFullAccess()
create($user)           → clinic_id && hasFullAccess()
update($user, $model)   → sameClinic() && hasFullAccess()
delete($user, $model)   → sameClinic() && hasFullAccess()
restore($user, $model)  → sameClinic() && hasFullAccess()
forceDelete($user, $model) → sameClinic() && hasFullAccess()
```

### AppointmentPolicy (excepciones)

- `viewAny`: solo requiere `clinic_id` (todos ven el listado, pero el service scopa por professional)
- `view`: si es viewer y `professional_id !== user.id` → denegado
- `create/update/delete`: requiere `hasFullAccess()`
- `issueInvoice`: requiere `hasFullAccess()`

### PatientPolicy (excepciones)

- `viewAny`: solo requiere `clinic_id`
- `view`: solo requiere `sameClinic()` (el controller aplica `stripFinancialData` después)

## Service Layer — Scoping

### AppointmentService::list()

```php
if ($user && $user->isViewer()) {
    $query->where('professional_id', $user->id);
}
```

Los profesionales solo ven citas donde son `professional_id`. El scoping se hace en service (no en policy) porque policy no puede filtrar colecciones.

### PatientController — Financial Data Stripping

```php
if (Auth::user()->isViewer()) {
    unset($data['available_credit'], $data['payments'], $data['packs']);
}
```

## Registration Flow

`RegisterController@__invoke`:
1. Crea Clinic con `subscription_status = 'trial'`, `status = 'trial'`
2. Asigna `profile_id` del perfil `admin` al nuevo usuario
3. Envía email de activación con link firmado

```php
$adminProfile = Profile::where('slug', 'admin')->first();
$user = User::create([
    'clinic_id' => $clinic->id,
    'password' => $data['password'],   // hashed automáticamente por el cast
    'profile_id' => $adminProfile?->id,
]);
```

## Testing

- `tests/Feature/Authorization/RoleBasedAccessTest.php` — 28 tests: helpers, appointments CRUD scoping, patients CRUD + financial data, payments/products/documents/packs access por perfil
- `tests/Feature/Authorization/PolicyAuthorizationTest.php` — Tests de tenancy cross-clinic + reglas de negocio (invoiced payments, refunds)
- Correr: `php artisan test --filter=Authorization`

## Known Pitfalls

1. **Profile no es Role:** User tiene `role` (owner/user) y `profile_id` (admin/manager/professional). Son conceptos distintos. No confundir. El `$attributes` default es `'role' => 'owner'`.
2. **hasFullAccess() requiere modelo cargado:** Si el User se crea sin especificar `profile_id`, la relación `profile` será null, pero `isOwner()` igual funciona por la columna `role`.
3. **DB default vs modelo en memoria:** La migración define `role` default `'owner'`, pero Laravel no refresca el modelo tras `create()`. Siempre usar `$attributes` en el modelo o pasar `'role' => 'owner'` explícito.
4. **Scoping en service, no en policy:** El filtro de vista profesional (`WHERE professional_id = ?`) va en `AppointmentService::list()`, no en `AppointmentPolicy::viewAny()`, porque las policies evalúan una acción, no filtran queries.
5. **Financial data stripping:** Se hace en `PatientController::index()/show()` después de la policy, no en `PatientsServices`, para no modificar la firma del service.
6. **CheckSubscriptionAccess:** Middleware que retorna 403 si la clínica no tiene `subscription_status` y `status`. Los tests que crean clínicas sin estos campos deben usar `withoutMiddleware(CheckSubscriptionAccess::class)`.
