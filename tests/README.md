# Tests

## Estructura

```
tests/
  Unit/                         ← Unitarios (sin DB, sin HTTP)
    ExampleTest.php             Placeholder

  Feature/                      ← Integración (HTTP + DB)
    Admin/
      GenerateClinicErrorTest.php     Genera error 500 + pagos fallidos para testing de backoffice
    Api/Auth/
      PasswordRecoveryApiTest.php     Recuperación de contraseña por API
      TeamUserLoginTest.php           Login de profesional creado desde equipo
    Appointments/
      AppointmentAvailabilityTest.php CRUD citas, solapamientos, reprogramación
    Auth/           (Laravel Breeze — tests estándar)
    Authorization/
      PolicyAuthorizationTest.php     Permisos cross-clinic, pagos reembolsados
      RoleBasedAccessTest.php         Roles owner/admin/manager/professional (31 tests)
    Backoffice/
      AdminAuthenticationTest.php     Login backoffice
      AdminAuthorizationTest.php      Permisos super-admin / readonly
      ClinicManagementTest.php        Gestión clínicas, suspender, reactivar, impersonar
    Billing/
      BillingCancellationTest.php     Cancelación suscripción, gracia read-only
      BillingLifecycleTest.php        Trial → paid → read-only → blocked
      StripeWebhookControllerTest.php Webhooks Stripe (checkout, invoice)
    Booking/
      AvailabilityEngineTest.php      Motor disponibilidad (slots, solapamientos, horizonte)
      BookingSettingsAdminTest.php    CRUD servicios, profesionales, horarios booking
      PublicBookingFlowTest.php       Flujo público: página, slots, crear/cancelar cita
    Clinic/
      ClinicScopeTest.php             Aislamiento multi-clínica (clinic_id scope)
    Documents/
      IssueAbonoTest.php              Creación factura rectificativa (abono)
    Payments/
      PaymentStoreTest.php            Pago con bono
    Profile/
      ProfileTest.php                 Perfil usuario (Breeze)
    Trials/
      TrialLifecycleTest.php          Día 1 → warning → read-only → churned
    ExampleTest.php                   Placeholder

modules/Notifications/Tests/Feature/   ← Tests del módulo de notificaciones
  AppointmentReminderJobsTest.php     Envío recordatorios 24h/2h + cadencia configurable
  EmailLogTest.php                    Log de emails, reenvío recordatorios
  ReminderNotificationsTest.php       Listado, detalle, reenvío recordatorios

  E2E/                          ← End-to-End (vacío — pendiente de Dusk/Playwright)
```

## Cómo ejecutar

| Comando | Qué ejecuta |
|---------|-------------|
| `php artisan test` | Todos los tests |
| `php artisan test --filter=RoleBasedAccess` | Tests de un archivo específico |
| `php artisan test --filter=test_professional_is_viewer` | Un test específico |
| `php artisan test tests/Feature/Authorization/` | Tests de una carpeta específica |
