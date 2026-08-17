# Planificación de recordatorios de citas

Los recordatorios de citas (24h y 2h antes de la cita) se envían mediante dos jobs programados en el scheduler de Laravel (`bootstrap/app.php`). Su **frecuencia de ejecución es variable** y se controla por configuración, sin tocar código.

## Jobs programados

| Nombre en scheduler | Job | Qué envía |
|---|---|---|
| `appointments:reminders-24h` | `Modules\Notifications\Application\Jobs\SendAppointmentReminder24hJob` | Recordatorio 24h antes de la cita |
| `appointments:reminders-2h` | `Modules\Notifications\Application\Jobs\SendAppointmentReminder2hJob` | Recordatorio 2h antes de la cita |

## Frecuencia variable (`REMINDER_INTERVAL_MINUTES`)

La cadencia de ambos jobs se define en **minutos** mediante la variable de entorno `REMINDER_INTERVAL_MINUTES`, leída desde `config/reminders.php`:

```env
# Cadencia de los jobs de recordatorios (minutos). Default: 15
REMINDER_INTERVAL_MINUTES=15
```

```php
// config/reminders.php
'interval_minutes' => (int) env('REMINDER_INTERVAL_MINUTES', 15),
```

En `bootstrap/app.php` el scheduler construye la expresión cron a partir de ese valor:

```php
$reminderInterval = max(1, (int) config('reminders.interval_minutes', 15));
$reminderCron = sprintf('*/%d * * * *', $reminderInterval);
```

- **Default**: 15 minutos si la variable no está definida.
- **Valores razonables**: 1–59 (la expresión `*/N` divide la hora).
- El guard `max(1, ...)` evita expresiones cron inválidas con valores 0 o negativos.
- Para cambiar la cadencia: ajustar `REMINDER_INTERVAL_MINUTES` en `.env` y reiniciar el scheduler (`schedule:work` en dev / cron en producción). No requiere cambios de código ni migraciones.

### Efecto sobre la hora de envío

Los jobs buscan citas cuya `start_time` caiga en la ventana `[now + N horas, now + N + 1 hora]` (`AppointmentReminderQueryService`), siendo N = 24 o 2. Al ejecutarse cada `REMINDER_INTERVAL_MINUTES`, un recordatorio puede enviarse hasta ese intervalo después de su hora nominal. Ejemplo con cadencia de 15 min: el recordatorio de 2h puede llegar entre 2h00m y 1h45m antes de la cita. Con cadencias altas (p. ej. 50–59 min) la ventana de 1h puede quedar parcialmente barrida, por lo que se recomienda usar valores ≤ 30.

## Idempotencia (sin duplicados)

Cada job es idempotente independientemente de la cadencia:

1. La consulta solo selecciona citas con `reminder_24h_sent_at` / `reminder_2h_sent_at` a NULL.
2. Al enviar, el job marca la columna `reminder_*_sent_at`, excluyéndola de ejecuciones futuras.
3. Cada envío queda auditado en la tabla `reminders` (estado `sent`/`failed`, reenvío manual desde el endpoint `POST /api/reminders/{reminder}/resend`).
4. `withoutOverlapping()` evita ejecuciones concurrentes del mismo job (los nombres `appointments:reminders-24h` / `appointments:reminders-2h` son la clave del lock; no renombrarlos sin más).

## Ejecución manual

Existen comandos artesanales para forzar el barrido fuera del scheduler:

```bash
php artisan reminders:send-24h
php artisan reminders:send-2h
```

Definidos en `routes/console.php`, invocan directamente el `handle()` de cada job.

## Ejecución del scheduler

- **Producción**: cron ejecutando `php artisan schedule:run` cada minuto (ver `docs/deployment/linux-cloud.md`).
- **Desarrollo (WAMP)**: no hay cron; `scripts/start_dev.py` incluye `php artisan schedule:work` en el arranque.

## Tareas programadas relacionadas

Para referencia completa del scheduler (`bootstrap/app.php`):

| Tarea | Cadencia | Notas |
|---|---|---|
| `appointments:reminders-24h` | `REMINDER_INTERVAL_MINUTES` (default 15 min) | Este documento |
| `appointments:reminders-2h` | `REMINDER_INTERVAL_MINUTES` (default 15 min) | Este documento |
| `PurgeExpiredClinics` | Diario 03:00 | Purga clínicas expiradas |
| `ProcessTrialLifecycle` | Cada 30 minutos | Hitos de trial |
