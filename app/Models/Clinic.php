<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Clinic extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'email', 'phone', 'address', 'nif', 'locality', 'province', 'country', 'zip', 'timezone', 'trial_ends_at', 'subscribed_at'
    ];

    protected $casts = [
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

    public function subscriptions(): HasMany
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

    public function currentSubscription()
    {
        // Preferir suscripción activa; si no existe, devolver la más reciente
        $active = $this->subscriptions()->where('status', 'active')->orderByDesc('id')->first();
        if ($active) {
            return $active;
        }

        return $this->subscriptions()->orderByDesc('id')->first();
    }

    public function isTrialActive(): bool
    {
        $sub = $this->currentSubscription();
        if (! $sub) return false;
        if (! isset($sub->trial_ends_at)) return false;
        return $sub->trial_ends_at->isFuture();
    }

    public function isSubscribed(): bool
    {
        $sub = $this->currentSubscription();
        if (! $sub) return false;
        return isset($sub->status) && $sub->status === 'active';
    }
}
