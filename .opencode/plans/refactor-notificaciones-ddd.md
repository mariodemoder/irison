# Plan de Refactor: Notificaciones → DDD (Fase 1 + 2)

## Objetivo

Migrar toda la funcionalidad de notificaciones a un bounded context `modules/Notifications/` siguiendo la arquitectura Lightweight DDD (Application/Domain/Infrastructure) y unificar el patrón de envío a Laravel Notification classes.

## Archivos a crear / mover

### Domain Layer

#### `modules/Notifications/Domain/Enums/NotificationChannel.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Database = 'database';
    case Sms = 'sms';
    case Push = 'push';
}
```

#### `modules/Notifications/Domain/Enums/NotificationStatus.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
```

#### `modules/Notifications/Domain/Enums/ReminderType.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum ReminderType: string
{
    case TwentyFourHours = '24h';
    case TwoHours = '2h';

    public function hoursBefore(): int
    {
        return match ($this) {
            self::TwentyFourHours => 24,
            self::TwoHours => 2,
        };
    }

    public function sentAtColumn(): string
    {
        return match ($this) {
            self::TwentyFourHours => 'reminder_24h_sent_at',
            self::TwoHours => 'reminder_2h_sent_at',
        };
    }
}
```

#### `modules/Notifications/Domain/Contracts/ReminderRepositoryInterface.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Models\ReminderLog;

interface ReminderRepositoryInterface
{
    public function findById(int $id): ?ReminderLog;

    public function create(
        int $clinicId,
        int $appointmentId,
        ReminderType $reminderType,
        string $recipientEmail,
        NotificationStatus $status,
        ?CarbonInterface $sentAt = null,
        ?string $errorMessage = null,
    ): ReminderLog;

    public function updateStatus(int $id, NotificationStatus $status, ?CarbonInterface $sentAt = null, ?string $errorMessage = null): void;

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findHistory(int $appointmentId, ReminderType $reminderType): array;

    public function count(): int;

    public function countByStatus(NotificationStatus $status): int;
}
```

#### `modules/Notifications/Domain/Models/ReminderLog.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonInterface;
use Modules\Notifications\Domain\Enums\NotificationChannel;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;

class ReminderLog
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly int $appointmentId,
        public readonly NotificationChannel $channel,
        public readonly ReminderType $reminderType,
        public readonly string $recipientEmail,
        public readonly NotificationStatus $status,
        public readonly ?CarbonInterface $sentAt = null,
        public readonly ?string $errorMessage = null,
        public readonly ?CarbonInterface $createdAt = null,
        public readonly ?CarbonInterface $updatedAt = null,
    ) {}

    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::Failed;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::Sent;
    }

    public function isQueued(): bool
    {
        return $this->status === NotificationStatus::Queued;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointmentId,
            'channel' => $this->channel->value,
            'reminder_type' => $this->reminderType->value,
            'recipient_email' => $this->recipientEmail,
            'status' => $this->status->value,
            'error_message' => $this->errorMessage,
            'sent_at' => $this->sentAt?->toDateTimeString(),
            'created_at' => $this->createdAt?->toDateTimeString(),
            'updated_at' => $this->updatedAt?->toDateTimeString(),
        ];
    }
}
```

### Infrastructure/Persistence

#### `modules/Notifications/Infrastructure/Persistence/ReminderEloquentModel.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use App\Models\Concerns\BelongsToClinic;
use App\Models\Clinic;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'reminders';

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'channel',
        'reminder_type',
        'recipient_email',
        'error_message',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'sent_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
```

#### `modules/Notifications/Infrastructure/Persistence/ReminderRepository.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Models\ReminderLog;

class ReminderRepository implements ReminderRepositoryInterface
{
    public function findById(int $id): ?ReminderLog
    {
        $model = ReminderEloquentModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function create(
        int $clinicId,
        int $appointmentId,
        ReminderType $reminderType,
        string $recipientEmail,
        NotificationStatus $status,
        ?CarbonInterface $sentAt = null,
        ?string $errorMessage = null,
    ): ReminderLog {
        $model = ReminderEloquentModel::create([
            'clinic_id' => $clinicId,
            'appointment_id' => $appointmentId,
            'channel' => 'email',
            'reminder_type' => $reminderType->value,
            'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
            'error_message' => $errorMessage,
            'sent_at' => $sentAt,
            'status' => $status->value,
        ]);

        return $this->toDomain($model);
    }

    public function updateStatus(int $id, NotificationStatus $status, ?CarbonInterface $sentAt = null, ?string $errorMessage = null): void
    {
        $model = ReminderEloquentModel::find($id);
        if ($model) {
            $model->update([
                'status' => $status->value,
                'sent_at' => $sentAt ?? $model->sent_at,
                'error_message' => $errorMessage ?? $model->error_message,
            ]);
        }
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ReminderEloquentModel::query()
            ->with([
                'appointment:id,clinic_id,patient_id,start_time,status',
                'appointment.patient:id,counter,first_name,last_name,email',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reminder_type'])) {
            $query->where('reminder_type', $filters['reminder_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate(DB::raw('COALESCE(sent_at, created_at)'), '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate(DB::raw('COALESCE(sent_at, created_at)'), '<=', $filters['to_date']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('recipient_email', 'like', "%{$term}%")
                    ->orWhereHas('appointment.patient', function (Builder $patientQuery) use ($term): void {
                        $patientQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('counter', 'like', "%{$term}%");
                    });
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findHistory(int $appointmentId, ReminderType $reminderType): array
    {
        return ReminderEloquentModel::query()
            ->where('appointment_id', $appointmentId)
            ->where('reminder_type', $reminderType->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReminderEloquentModel $m) => [
                'id' => $m->id,
                'status' => $m->status,
                'recipient_email' => $m->recipient_email,
                'error_message' => $m->error_message,
                'sent_at' => $m->sent_at,
                'created_at' => $m->created_at,
            ])
            ->values()
            ->all();
    }

    public function count(): int
    {
        return ReminderEloquentModel::query()->count();
    }

    public function countByStatus(NotificationStatus $status): int
    {
        return ReminderEloquentModel::query()->where('status', $status->value)->count();
    }

    private function toDomain(ReminderEloquentModel $model): ReminderLog
    {
        return new ReminderLog(
            id: $model->id,
            clinicId: (int) $model->clinic_id,
            appointmentId: (int) $model->appointment_id,
            channel: \Modules\Notifications\Domain\Enums\NotificationChannel::from($model->channel),
            reminderType: ReminderType::from($model->reminder_type),
            recipientEmail: (string) $model->recipient_email,
            status: NotificationStatus::from($model->status),
            sentAt: $model->sent_at,
            errorMessage: $model->error_message,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}
```

### Domain/Services

#### `modules/Notifications/Domain/Services/ReminderDomainService.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Services;

use App\Models\Appointment;
use DomainException;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Infrastructure\Persistence\ReminderRepository;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Throwable;

class ReminderDomainService
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function sendAppointmentReminder(
        Appointment $appointment,
        ReminderType $reminderType,
        bool $markAppointmentSent = true,
        bool $throwOnFailure = false,
    ): array {
        $appointment->loadMissing(['patient', 'clinic']);

        $email = trim((string) $appointment->patient?->email);

        if ($email === '') {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Failed,
                errorMessage: 'Paciente sin email para enviar recordatorio.',
            );

            Log::warning('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'reason' => 'missing_email',
            ]);

            if ($throwOnFailure) {
                throw new DomainException('Paciente sin email para enviar recordatorio.');
            }

            return ['reminder' => $log, 'sent' => false];
        }

        try {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Queued,
                sentAt: now(),
            );

            Log::info('reminder.queued', [
                'event' => 'reminder.queued',
                'result' => 'queued',
                'reminder_id' => $log->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'recipient_domain' => $this->extractEmailDomain($email),
            ]);

            return ['reminder' => $log, 'sent' => true];
        } catch (Throwable $e) {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Failed,
                errorMessage: $e->getMessage(),
            );

            Log::error('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'reminder_id' => $log->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'recipient_domain' => $this->extractEmailDomain($email),
                'error_code' => class_basename($e),
            ]);

            if ($throwOnFailure) {
                throw new DomainException($e->getMessage());
            }

            return ['reminder' => $log, 'sent' => false];
        }
    }

    private function extractEmailDomain(string $email): ?string
    {
        $normalized = trim(strtolower($email));
        if ($normalized === '' || !str_contains($normalized, '@')) {
            return null;
        }
        return substr($normalized, strpos($normalized, '@') + 1) ?: null;
    }
}
```

### Application Layer

#### `modules/Notifications/Application/DTOs/ReminderFilterData.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\DTOs;

class ReminderFilterData
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $status = null,
        public readonly ?string $reminderType = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            q: $validated['q'] ?? null,
            status: $validated['status'] ?? null,
            reminderType: $validated['reminder_type'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 15),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'q' => $this->q,
            'status' => $this->status,
            'reminder_type' => $this->reminderType,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null);
    }
}
```

#### `modules/Notifications/Application/DTOs/ReminderResponseData.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\DTOs;

class ReminderResponseData
{
    public static function fromReminderWithAppointment(
        \Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel $reminder,
        ?array $history = null,
        ?array $clinic = null,
    ): array {
        $patient = $reminder->appointment?->patient;

        $payload = [
            'id' => $reminder->id,
            'appointment_id' => $reminder->appointment_id,
            'channel' => $reminder->channel,
            'reminder_type' => $reminder->reminder_type,
            'recipient_email' => $reminder->recipient_email,
            'status' => $reminder->status,
            'error_message' => $reminder->error_message,
            'sent_at' => $reminder->sent_at,
            'created_at' => $reminder->created_at,
            'appointment' => $reminder->appointment ? [
                'id' => $reminder->appointment->id,
                'start_time' => $reminder->appointment->start_time,
                'status' => $reminder->appointment->status,
                'reminder_24h_sent_at' => $reminder->appointment->reminder_24h_sent_at,
                'reminder_2h_sent_at' => $reminder->appointment->reminder_2h_sent_at,
            ] : null,
            'patient' => $patient ? [
                'id' => $patient->id,
                'counter' => $patient->counter,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ] : null,
        ];

        if ($clinic !== null) {
            $payload['clinic'] = $clinic;
        }
        if ($history !== null) {
            $payload['history'] = $history;
        }

        return $payload;
    }
}
```

#### `modules/Notifications/Application/UseCases/ListRemindersQuery.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\ReminderFilterData;
use Modules\Notifications\Application\DTOs\ReminderResponseData;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\NotificationStatus;

class ListRemindersQuery
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function execute(ReminderFilterData $filter): array
    {
        $paginator = $this->repository->paginate($filter->toArray(), $filter->perPage);

        return [
            'data' => collect($paginator->items())
                ->map(fn ($reminder) => ReminderResponseData::fromReminderWithAppointment($reminder))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'count' => $this->repository->count(),
                'sent_count' => $this->repository->countByStatus(NotificationStatus::Sent),
                'failed_count' => $this->repository->countByStatus(NotificationStatus::Failed),
            ],
        ];
    }
}
```

#### `modules/Notifications/Application/UseCases/ShowReminderDetailQuery.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\ReminderResponseData;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ShowReminderDetailQuery
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function execute(ReminderEloquentModel $reminder): array
    {
        $reminder->load([
            'appointment:id,clinic_id,patient_id,start_time,status,reminder_24h_sent_at,reminder_2h_sent_at',
            'appointment.patient:id,counter,first_name,last_name,email,phone',
            'appointment.clinic:id,name,timezone,email,phone',
        ]);

        $history = $this->repository->findHistory(
            $reminder->appointment_id,
            ReminderType::from($reminder->reminder_type),
        );

        $clinic = $reminder->appointment?->clinic ? [
            'id' => $reminder->appointment->clinic->id,
            'name' => $reminder->appointment->clinic->name,
            'timezone' => $reminder->appointment->clinic->timezone,
            'email' => $reminder->appointment->clinic->email,
            'phone' => $reminder->appointment->clinic->phone,
        ] : null;

        return ReminderResponseData::fromReminderWithAppointment($reminder, $history, $clinic);
    }
}
```

#### `modules/Notifications/Application/UseCases/ResendReminderCommand.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use DomainException;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Services\ReminderDomainService;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ResendReminderCommand
{
    public function __construct(
        private readonly ReminderDomainService $domainService,
    ) {}

    public function execute(ReminderEloquentModel $reminder): ReminderEloquentModel
    {
        $appointment = $reminder->appointment()
            ->with(['patient', 'clinic'])
            ->firstOrFail();

        $result = $this->domainService->sendAppointmentReminder(
            $appointment,
            ReminderType::from((string) $reminder->reminder_type),
            markAppointmentSent: true,
            throwOnFailure: true,
        );

        $reminderLog = $result['reminder'];

        // Return the Eloquent model for the response
        return ReminderEloquentModel::findOrFail($reminderLog->id);
    }
}
```

#### `modules/Notifications/Application/Jobs/AppointmentReminderQueryService.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AppointmentReminderQueryService
{
    public function getAppointmentsFor24hReminder(?CarbonImmutable $now = null): Collection
    {
        return $this->getAppointmentsForWindow('reminder_24h_sent_at', 24, $now);
    }

    public function getAppointmentsFor2hReminder(?CarbonImmutable $now = null): Collection
    {
        return $this->getAppointmentsForWindow('reminder_2h_sent_at', 2, $now);
    }

    private function getAppointmentsForWindow(string $sentAtColumn, int $hoursAhead, ?CarbonImmutable $now = null): Collection
    {
        [$from, $to] = $this->buildWindow($hoursAhead, $now);

        return Appointment::query()
            ->with(['patient:id,first_name,last_name,email', 'clinic:id,name,timezone'])
            ->where('status', 'scheduled')
            ->whereNotNull('patient_id')
            ->whereBetween('start_time', [$from, $to])
            ->whereNull($sentAtColumn)
            ->whereHas('patient', function ($query): void {
                $query
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
            })
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    private function buildWindow(int $hoursAhead, ?CarbonImmutable $now = null): array
    {
        $base = $now ?? CarbonImmutable::now();

        return [
            $base->addHours($hoursAhead),
            $base->addHours($hoursAhead + 1),
        ];
    }
}
```

#### `modules/Notifications/Application/Jobs/SendAppointmentReminder24hJob.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Application\Jobs\AppointmentReminderQueryService;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Services\ReminderDomainService;

class SendAppointmentReminder24hJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        AppointmentReminderQueryService $queryService,
        ReminderDomainService $domainService,
    ): void {
        $appointments = $queryService->getAppointmentsFor24hReminder();

        foreach ($appointments as $appointment) {
            $result = $domainService->sendAppointmentReminder($appointment, ReminderType::TwentyFourHours);

            if ($result['sent']) {
                $appointment->forceFill([
                    'reminder_24h_sent_at' => now(),
                ])->save();

                $appointment->patient->notify(
                    new \Modules\Notifications\Infrastructure\Notifications\AppointmentReminderNotification(
                        $appointment,
                        ReminderType::TwentyFourHours,
                    )
                );
            }
        }
    }
}
```

#### `modules/Notifications/Application/Jobs/SendAppointmentReminder2hJob.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Application\Jobs\AppointmentReminderQueryService;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Services\ReminderDomainService;

class SendAppointmentReminder2hJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        AppointmentReminderQueryService $queryService,
        ReminderDomainService $domainService,
    ): void {
        $appointments = $queryService->getAppointmentsFor2hReminder();

        foreach ($appointments as $appointment) {
            $result = $domainService->sendAppointmentReminder($appointment, ReminderType::TwoHours);

            if ($result['sent']) {
                $appointment->forceFill([
                    'reminder_2h_sent_at' => now(),
                ])->save();

                $appointment->patient->notify(
                    new \Modules\Notifications\Infrastructure\Notifications\AppointmentReminderNotification(
                        $appointment,
                        ReminderType::TwoHours,
                    )
                );
            }
        }
    }
}
```

### Infrastructure/Notifications (Laravel Notification classes)

#### `modules/Notifications/Infrastructure/Notifications/AppointmentReminderNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Notifications;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Domain\Enums\ReminderType;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
        private readonly ReminderType $reminderType,
    ) {}

    public function via(Patient $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Patient $notifiable): MailMessage
    {
        $hoursBefore = $this->reminderType->hoursBefore();
        $clinic = $this->appointment->clinic;
        $patientName = $notifiable->first_name;

        return (new MailMessage)
            ->subject("Recordatorio de cita - {$clinic->name}")
            ->view('emails.appointment-reminder', [
                'patientName' => $patientName,
                'clinicName' => $clinic->name,
                'clinicPhone' => $clinic->phone,
                'appointmentDate' => $this->appointment->start_time->format('d/m/Y'),
                'appointmentTime' => $this->appointment->start_time->format('H:i'),
                'hoursBefore' => $hoursBefore,
                'clinicAddress' => $clinic->address ?? '',
            ]);
    }
}
```

#### `modules/Notifications/Infrastructure/Notifications/SubscriptionUpgradeRequestedNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Notifications;

use App\Mail\SubscriptionRequestMail;
use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'upgrade_requested',
            'request_id' => $this->request->id,
            'plan' => $this->request->plan,
            'clinic_name' => $this->request->clinic->name ?? '',
            'requester_name' => $this->request->requester?->name ?? '',
            'message' => "Se ha solicitado una actualización al plan {$this->request->plan}.",
        ]);
    }
}
```

#### `modules/Notifications/Infrastructure/Notifications/CheckoutLinkGeneratedNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class CheckoutLinkGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'checkout_link_generated',
            'request_id' => $this->request->id,
            'checkout_url' => $this->request->checkout_url ?? '',
            'message' => 'El enlace de pago para la actualización está listo.',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = new \App\Mail\UpgradeCheckoutLinkMail($this->request);

        return (new MailMessage)
            ->subject($mail->subject ?? 'Enlace de pago para actualización')
            ->view('emails.upgrade-checkout-link', [
                'checkoutUrl' => $this->request->checkout_url,
                'clinicName' => $this->request->clinic->name ?? '',
                'plan' => $this->request->plan,
            ]);
    }
}
```

#### `modules/Notifications/Infrastructure/Notifications/PaymentCompletedNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class PaymentCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'payment_completed',
            'request_id' => $this->request->id,
            'amount' => $this->request->amount ?? 0,
            'message' => 'El pago para la actualización se ha completado.',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pago completado - Actualización de plan')
                    ->view('emails.payment-completed', [
                        'clinicName' => $this->request->clinic->name ?? '',
                        'plan' => $this->request->plan,
                    ]);
    }
}
```

#### `modules/Notifications/Infrastructure/Notifications/SubscriptionUpgradedNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'subscription_upgraded',
            'request_id' => $this->request->id,
            'plan' => $this->request->plan,
            'message' => "La suscripción se ha actualizado al plan {$this->request->plan}.",
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Suscripción actualizada')
            ->view('emails.subscription-upgraded-notification', [
                'clinicName' => $this->request->clinic->name ?? '',
                'plan' => $this->request->plan,
                'invoiceUrl' => $this->resolveInvoiceUrl(),
            ]);
    }
}
```

### Infrastructure/Controllers

#### `modules/Notifications/Infrastructure/Controllers/ReminderController.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\DTOs\ReminderFilterData;
use Modules\Notifications\Application\UseCases\ListRemindersQuery;
use Modules\Notifications\Application\UseCases\ResendReminderCommand;
use Modules\Notifications\Application\UseCases\ShowReminderDetailQuery;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ListRemindersQuery $listQuery,
        private readonly ShowReminderDetailQuery $showQuery,
        private readonly ResendReminderCommand $resendCommand,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ReminderEloquentModel::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:sent,failed'],
            'reminder_type' => ['nullable', 'in:24h,2h'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->listQuery->execute(ReminderFilterData::fromRequest($validated)));
    }

    public function show(ReminderEloquentModel $reminder): JsonResponse
    {
        Gate::authorize('view', $reminder);

        return response()->json($this->showQuery->execute($reminder));
    }

    public function resend(ReminderEloquentModel $reminder): JsonResponse
    {
        Gate::authorize('update', $reminder);

        try {
            $newReminder = $this->resendCommand->execute($reminder);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Recordatorio reenviado correctamente.',
            'data' => [
                'id' => $newReminder->id,
                'status' => $newReminder->status,
                'sent_at' => $newReminder->sent_at,
            ],
        ]);
    }
}
```

### Infrastructure/Policies

#### `modules/Notifications/Infrastructure/Policies/ReminderPolicy.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Policies;

use App\Models\User;
use App\Policies\BasePolicy;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ReminderPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasFullAccess();
    }
}
```

### Infrastructure/Providers

#### `modules/Notifications/Infrastructure/Providers/NotificationsServiceProvider.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Infrastructure\Persistence\ReminderRepository;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ReminderRepositoryInterface::class,
            ReminderRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../Config/notifications.php', 'notifications');
    }
}
```

### Routes

#### `modules/Notifications/Routes/api.php`
```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Infrastructure\Controllers\ReminderController;

Route::middleware(['auth:sanctum', 'clinic', 'check.subscription'])->group(function () {
    Route::get('reminders', [ReminderController::class, 'index']);
    Route::get('reminders/{reminder}', [ReminderController::class, 'show']);
    Route::post('reminders/{reminder}/resend', [ReminderController::class, 'resend']);
});
```

### Config

#### `modules/Notifications/Config/notifications.php`
```php
<?php

return [
    'channel' => env('NOTIFICATION_CHANNEL', 'email'),
    'cancellation_notification_to' => env('CANCELLATION_NOTIFICATION_TO', 'admin@dueleahi.com'),
];
```

### Backward Compatibility Aliases (to be created in app/ namespace)

#### `app/Models/Reminder.php` (replace content)
```php
<?php

namespace App\Models;

use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class Reminder extends ReminderEloquentModel
{
    // Backward compatibility alias for existing code
}
```

### Listeners (moved + refactored)

#### `modules/Notifications/Infrastructure/Listeners/SendUpgradeRequestNotification.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Listeners;

use App\Events\UpgradeRequested;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Infrastructure\Notifications\SubscriptionUpgradeRequestedNotification;

class SendUpgradeRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpgradeRequested $event): void
    {
        $request = $event->request;

        try {
            $admins = $request->clinic->getAdmins();

            foreach ($admins as $admin) {
                $admin->notify(new SubscriptionUpgradeRequestedNotification($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send upgrade request notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

#### `modules/Notifications/Infrastructure/Listeners/SendCheckoutEmail.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Listeners;

use App\Events\CheckoutCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Infrastructure\Notifications\CheckoutLinkGeneratedNotification;

class SendCheckoutEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CheckoutCreated $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for clinic checkout notification');
            }

            $recipient->notify(new CheckoutLinkGeneratedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send checkout email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

#### `modules/Notifications/Infrastructure/Listeners/SendPaymentConfirmationEmail.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Listeners;

use App\Events\PaymentCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Infrastructure\Notifications\PaymentCompletedNotification;

class SendPaymentConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentCompleted $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for payment notification');
            }

            $recipient->notify(new PaymentCompletedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

#### `modules/Notifications/Infrastructure/Listeners/UpgradeSubscription.php`
```php
<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Listeners;

use App\Events\SubscriptionUpgraded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Infrastructure\Notifications\SubscriptionUpgradedNotification;

class UpgradeSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionUpgraded $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for upgraded notification');
            }

            $recipient->notify(new SubscriptionUpgradedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription upgraded notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

## Steps

### Step 1: Create module directory structure (done)

### Step 2: Create Domain layer files
- Enums: NotificationChannel, NotificationStatus, ReminderType
- Contracts: ReminderRepositoryInterface
- Models: ReminderLog
- Services: ReminderDomainService

### Step 3: Create Infrastructure/Persistence files
- ReminderEloquentModel (the actual Eloquent model, moved from app/Models)
- ReminderRepository (implements the interface)

### Step 4: Create Application layer files
- DTOs: ReminderFilterData, ReminderResponseData
- UseCases: ListRemindersQuery, ShowReminderDetailQuery, ResendReminderCommand
- Jobs: AppointmentReminderQueryService, SendAppointmentReminder24hJob, SendAppointmentReminder2hJob

### Step 5: Create Infrastructure layer files
- Controllers: ReminderController (moved from app/Http/Controllers/Api)
- Policies: ReminderPolicy (moved from app/Policies)
- Notifications: all Laravel Notification classes (moved from app/Notifications)
- Mail: all Mailables (moved from app/Mail - kept for backward compat)
- Listeners: all subscription/consent listeners (moved from app/Listeners)
- Providers: NotificationsServiceProvider
- Requests: (validation moved inline to controller for now)

### Step 6: Create Routes
- Move /api/reminders routes from routes/api.php to modules/Notifications/Routes/api.php

### Step 7: Update bootstrap/providers.php
- Add Modules\Notifications\Infrastructure\Providers\NotificationsServiceProvider::class

### Step 8: Create backward compatibility alias
- Update app/Models/Reminder.php to extend ReminderEloquentModel

### Step 9: Update EventServiceProvider
- Point listeners to new namespace: Modules\Notifications\Infrastructure\Listeners\*

### Step 10: Remove old routes from routes/api.php
- Remove lines 107-109 (reminder routes)

### Step 11: Move tests
- Move ReminderNotificationsTest to modules/Notifications/Tests/Feature/

### Step 12: Run tests
- php artisan test --filter=Reminder

## Backward Compatibility

- `App\Models\Reminder` extends `Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel` → all existing code continues to work
- `App\Services\Reminders\ReminderService` is NOT removed until Phase 4; it can be deprecated
- All API response shapes remain identical
- The `reminders` table schema is unchanged
