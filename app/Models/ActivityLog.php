<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'description',
        'metadata',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
