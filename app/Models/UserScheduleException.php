<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScheduleException extends Model
{
    protected $fillable = [
        'user_id', 'date', 'end_date', 'start_time', 'end_time', 'reason',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
