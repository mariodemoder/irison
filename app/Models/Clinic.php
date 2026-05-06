<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;


class Clinic extends Model
{
    use Billable, SoftDeletes;

    protected $fillable = [
        'name', 'legal_name', 'email', 'phone', 'address', 'nif', 'locality', 'province', 'country', 'zip', 'timezone', 'business_hours', 'closed_days', 'trial_ends_at', 'subscription_status', 'subscribed_at', 'invoice_background_path', 'stripe_id', 'pm_type', 'pm_last_four'
    ];

    protected $casts = [
        'business_hours' => 'array',
        'closed_days' => 'array',
        'trial_ends_at' => 'datetime',
        'subscribed_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function saasSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Bonus::class, 'clinic_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class, 'clinic_id');
    }

    public function appointmentTypes(): HasMany
    {
        return $this->hasMany(AppointmentType::class, 'clinic_id');
    }

    public function bonusTypes(): HasMany
    {
        return $this->hasMany(BonusType::class, 'clinic_id');
    }

    public function currentSubscription()
    {
        // Preferir suscripción activa; si no existe, devolver la más reciente
        $active = $this->saasSubscriptions()->where('status', 'active')->orderByDesc('id')->first();
        if ($active) {
            return $active;
        }

        return $this->saasSubscriptions()->orderByDesc('id')->first();
    }

    public function isTrialActive(): bool
    {
        if ($this->normalizedSubscriptionStatus() !== 'trial') {
            return false;
        }

        if (! $this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    public function isSubscribed(): bool
    {
        return $this->normalizedSubscriptionStatus() === 'active';
    }

    public static function weekNoTransactionForClinic(int $clinicId, ?Carbon $currentDate = null): bool
    {
        $clinic = static::query()->find($clinicId);
        if (! $clinic) {
            return false;
        }

        return $clinic->isInReadOnlyNoTransactionsWindow($currentDate);
    }

    public function weekNoTransaction(?Carbon $currentDate = null): bool
    {
        return $this->isInReadOnlyNoTransactionsWindow($currentDate);
    }

    public function isInReadOnlyNoTransactionsWindow(?Carbon $currentDate = null): bool
    {
        $status = $this->normalizedSubscriptionStatus();
        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return $this->isInCancellationGracePeriod($currentDate);
        }

        if ($status !== 'trial') {
            return false;
        }

        $trialEndsRaw = $this->trial_ends_at;
        if (! $trialEndsRaw) {
            return false;
        }

        $trialEndsAt = $trialEndsRaw instanceof Carbon
            ? $trialEndsRaw->copy()
            : Carbon::parse((string) $trialEndsRaw);

        $now = ($currentDate ?? now())->copy();
        if ($now->lessThanOrEqualTo($trialEndsAt)) {
            return false;
        }

        return $now->lessThanOrEqualTo($trialEndsAt->addDays(7));
    }

    public function isInCancellationGracePeriod(?Carbon $currentDate = null): bool
    {
        if (! in_array($this->normalizedSubscriptionStatus(), ['canceled', 'cancelled'], true)) {
            return false;
        }

        $graceEndsAt = $this->cancellationGraceEndsAt();
        if (! $graceEndsAt) {
            return false;
        }

        $now = ($currentDate ?? now())->copy();

        return $now->lessThanOrEqualTo($graceEndsAt);
    }

    public function cancellationGraceDaysLeft(?Carbon $currentDate = null): ?int
    {
        if (! $this->isInCancellationGracePeriod($currentDate)) {
            return null;
        }

        $graceEndsAt = $this->cancellationGraceEndsAt();
        if (! $graceEndsAt) {
            return null;
        }

        $now = ($currentDate ?? now())->copy();
        $secondsLeft = $now->diffInSeconds($graceEndsAt, false);

        if ($secondsLeft < 0) {
            return null;
        }

        return (int) ceil($secondsLeft / 86400);
    }

    private function cancellationGraceEndsAt(): ?Carbon
    {
        $latestCanceled = $this->saasSubscriptions()
            ->whereIn('status', ['canceled', 'cancelled'])
            ->orderByDesc('id')
            ->first();

        $graceEndsRaw = $latestCanceled?->current_period_end;
        if (! $graceEndsRaw) {
            return null;
        }

        return $graceEndsRaw instanceof Carbon
            ? $graceEndsRaw->copy()
            : Carbon::parse((string) $graceEndsRaw);
    }

    private function normalizedSubscriptionStatus(): string
    {
        return strtolower(trim((string) ($this->subscription_status ?? 'inactive')));
    }
}
