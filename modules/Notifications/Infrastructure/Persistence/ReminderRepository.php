<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\NotificationChannel;
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
            channel: NotificationChannel::from($model->channel),
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
