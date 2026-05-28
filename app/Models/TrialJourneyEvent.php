<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialJourneyEvent extends Model
{
    protected $fillable = [
        'clinic_id',
        'event_key',
        'sent_at',
        'payload',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
