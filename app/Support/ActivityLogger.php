<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActivityLogger
{
    private static ?bool $hasTable = null;

    public static function log(
        int $tenantId,
        ?int $userId,
        string $event,
        string $description,
        ?array $metadata = null,
        ?string $ip = null,
    ): void {
        if ($tenantId <= 0 || trim($event) === '') {
            return;
        }

        try {
            if (! self::canWrite()) {
                return;
            }

            ActivityLog::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'event' => trim($event),
                'description' => trim($description),
                'metadata' => $metadata,
                'ip' => $ip ? mb_substr(trim($ip), 0, 64) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // El activity log nunca debe romper el flujo principal.
        }
    }

    private static function canWrite(): bool
    {
        if (self::$hasTable === null) {
            self::$hasTable = Schema::hasTable('activity_logs');
        }

        return self::$hasTable;
    }
}
