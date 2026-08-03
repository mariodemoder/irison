<?php

declare(strict_types=1);

namespace Modules\Activity\Infrastructure\Persistence;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Activity\Application\DTOs\ActivityFilterData;
use Modules\Activity\Domain\Contracts\ActivityRepositoryInterface;

class ActivityRepository implements ActivityRepositoryInterface
{
    public function search(int $clinicId, ActivityFilterData $filter): array
    {
        $query = ActivityLogQueryModel::query()
            ->with('user:id,name')
            ->where('tenant_id', $clinicId)
            ->where('event', '!=', 'login');

        if ($filter->q !== '') {
            $like = '%' . mb_strtolower($filter->q) . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(event) LIKE ?', [$like]);
            });
        }

        if ($filter->event !== null) {
            $query->where('event', $filter->event);
        }

        if ($filter->user_id !== null) {
            $query->where('user_id', $filter->user_id);
        }

        if ($filter->entity !== null) {
            $query->where('metadata->entity', $filter->entity);
        }

        if ($filter->from_date !== null) {
            $query->where('created_at', '>=', Carbon::parse($filter->from_date)->startOfDay());
        }

        if ($filter->to_date !== null) {
            $query->where('created_at', '<=', Carbon::parse($filter->to_date)->endOfDay());
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($filter->per_page);

        return [
            'data' => $this->mapItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function mapItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->map(function (ActivityLogQueryModel $log) {
            $metadata = $log->metadata ?? [];

            return [
                'id' => (int) $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'event' => (string) $log->event,
                'description' => (string) $log->description,
                'entity' => isset($metadata['entity']) ? (string) $metadata['entity'] : null,
                'entity_id' => isset($metadata['entity_id']) ? (int) $metadata['entity_id'] : null,
                'metadata' => $metadata,
                'ip' => $log->ip,
                'created_at' => $log->created_at?->toISOString(),
            ];
        })->values()->all();
    }
}
