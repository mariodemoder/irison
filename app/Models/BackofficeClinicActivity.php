<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackofficeClinicActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'admin_user_id',
        'target_user_id',
        'event',
        'result',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (BackofficeClinicActivity $activity) {
            if ($activity->clinic_id) {
                Clinic::withoutGlobalScopes()->where('id', $activity->clinic_id)->update(['last_activity_at' => now()]);
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
