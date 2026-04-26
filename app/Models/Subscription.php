<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'clinic_id', 'status', 'trial_ends_at', 'current_period_end',
        'stripe_customer_id', 'stripe_subscription_id'
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $subscription): void {
            $clinic = $subscription->clinic;
            if (! $clinic) {
                return;
            }

            $status = strtolower(trim((string) ($subscription->status ?? 'inactive')));
            $normalizedStatus = match ($status) {
                'trial' => 'trial',
                'active' => 'active',
                'past_due' => 'past_due',
                'canceled', 'cancelled' => 'canceled',
                default => 'inactive',
            };

            $clinic->subscription_status = $normalizedStatus;

            if ($normalizedStatus === 'trial' && $subscription->trial_ends_at) {
                $clinic->trial_ends_at = $subscription->trial_ends_at;
            }

            if ($normalizedStatus === 'active' && ! $clinic->subscribed_at) {
                $clinic->subscribed_at = now();
            }

            if (in_array($normalizedStatus, ['canceled', 'inactive'], true)) {
                $clinic->subscribed_at = null;
            }

            if ($clinic->isDirty(['subscription_status', 'trial_ends_at', 'subscribed_at'])) {
                $clinic->saveQuietly();
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
