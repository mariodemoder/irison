<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRequest extends Model
{
    use BelongsToClinic;

    public const TYPE_PLAN_CHANGE = 'plan_change';

    public const TYPE_REACTIVATION = 'reactivation';

    protected $fillable = [
        'clinic_id',
        'type',
        'current_plan',
        'requested_plan',
        'status',
        'comments',
        'reviewer_comments',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'completed_at',
        'stripe_checkout_session_id',
        'checkout_url',
    ];

    public function isReactivation(): bool
    {
        return ($this->type ?? self::TYPE_PLAN_CHANGE) === self::TYPE_REACTIVATION;
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }
}
