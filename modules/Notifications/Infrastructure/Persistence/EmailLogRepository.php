<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmailLogRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = EmailLogEloquentModel::query()
            ->with([
                'patient:id,counter,first_name,last_name,email,phone',
                'appointment:id,clinic_id,patient_id,start_time,status',
                'clinic:id,name,email,phone,timezone',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
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
                $builder->where('to_email', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($term): void {
                        $patientQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('counter', 'like', "%{$term}%");
                    });
            });
        }

        return $query
            ->orderByDesc(DB::raw('COALESCE(sent_at, created_at)'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function count(): int
    {
        return EmailLogEloquentModel::query()->count();
    }

    public function countByStatus(string $status): int
    {
        return EmailLogEloquentModel::query()->where('status', $status)->count();
    }
}
