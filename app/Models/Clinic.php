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

    public const PLAN_USER_LIMITS = [
        'basic' => 1,
        'pro' => 10,
        'enterprise' => -1,
    ];

    protected $fillable = [
        'name', 'slug', 'legal_name', 'email', 'phone', 'address', 'nif', 'locality', 'province', 'country', 'zip', 'timezone', 'business_hours', 'closed_days', 'max_users', 'trial_ends_at', 'subscription_status', 'status', 'plan', 'stripe_customer_id', 'suspended_at', 'churned_at', 'last_activity_at', 'subscribed_at', 'subscription_provider', 'subscription_reference', 'invoice_background_path', 'theme_color', 'stripe_id', 'pm_type', 'pm_last_four'
    ];

    protected $casts = [
        'business_hours' => 'array',
        'closed_days' => 'array',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'churned_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'subscribed_at' => 'datetime',
        'max_users' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function professions(): HasMany
    {
        return $this->hasMany(Profession::class);
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

    public function backofficeActivities(): HasMany
    {
        return $this->hasMany(BackofficeClinicActivity::class, 'clinic_id');
    }

    public function ownerUser()
    {
        return $this->hasOne(User::class)->where('role', 'owner')->orderBy('id');
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
        if (! in_array($this->normalizedSubscriptionStatus(), ['trial', 'trial_warning'], true)) {
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
            return $this->isInCancellationReadOnlyWindow($currentDate);
        }

        if (! in_array($status, ['trial', 'trial_warning'], true)) {
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

        $graceDays = max((int) config('billing.trial_grace_days', 7), 0);

        return $now->lessThanOrEqualTo($trialEndsAt->addDays($graceDays));
    }

    public function isInCancellationGracePeriod(?Carbon $currentDate = null): bool
    {
        return $this->isInCancellationPaidWindow($currentDate);
    }

    public function isInCancellationPaidWindow(?Carbon $currentDate = null): bool
    {
        if (! in_array($this->normalizedSubscriptionStatus(), ['canceled', 'cancelled'], true)) {
            return false;
        }

        $paidEndsAt = $this->cancellationGraceEndsAt();
        if (! $paidEndsAt) {
            return false;
        }

        $now = ($currentDate ?? now())->copy();

        return $now->lessThanOrEqualTo($paidEndsAt);
    }

    public function isInCancellationReadOnlyWindow(?Carbon $currentDate = null): bool
    {
        if (! in_array($this->normalizedSubscriptionStatus(), ['canceled', 'cancelled'], true)) {
            return false;
        }

        $paidEndsAt = $this->cancellationGraceEndsAt();
        if (! $paidEndsAt) {
            return false;
        }

        $now = ($currentDate ?? now())->copy();
        if ($now->lessThanOrEqualTo($paidEndsAt)) {
            return false;
        }

        $readOnlyDays = max((int) config('billing.cancellation_read_only_days', 7), 0);

        return $now->lessThanOrEqualTo($paidEndsAt->addDays($readOnlyDays));
    }

    public function cancellationGraceDaysLeft(?Carbon $currentDate = null): ?int
    {
        $paidEndsAt = $this->cancellationGraceEndsAt();
        if (! $paidEndsAt) {
            return null;
        }

        $now = ($currentDate ?? now())->copy();
        
        // Si aún estamos en el periodo pagado
        if ($now->lessThanOrEqualTo($paidEndsAt)) {
            $secondsLeft = $now->diffInSeconds($paidEndsAt, false);
            return (int) max(ceil($secondsLeft / 86400), 0);
        }

        return null;
    }

    public function cancellationReadOnlyDaysLeft(?Carbon $currentDate = null): ?int
    {
        if (! $this->isInCancellationReadOnlyWindow($currentDate)) {
            return null;
        }

        $paidEndsAt = $this->cancellationGraceEndsAt();
        if (! $paidEndsAt) {
            return null;
        }

        $readOnlyDays = max((int) config('billing.cancellation_read_only_days', 7), 0);
        $readOnlyEndsAt = $paidEndsAt->copy()->addDays($readOnlyDays);

        $now = ($currentDate ?? now())->copy();
        $secondsLeft = $now->diffInSeconds($readOnlyEndsAt, false);

        return (int) max(ceil($secondsLeft / 86400), 0);
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

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function tenantStatus(): string
    {
        if ($this->isSuspended()) {
            return 'suspended';
        }

        $status = $this->normalizedSubscriptionStatus();
        if (in_array($status, ['canceled', 'cancelled'], true)) {
            if ($this->isInCancellationGracePeriod()) {
                return 'cancelled';
            }

            return 'expired';
        }

        if (in_array($status, ['trial', 'trial_warning'], true)) {
            if ($this->isTrialActive() || $this->isInReadOnlyNoTransactionsWindow()) {
                return 'trial';
            }

            return 'expired';
        }

        return match ($status) {
            'active' => 'active',
            'past_due' => 'past_due',
            default => 'expired',
        };
    }
}
