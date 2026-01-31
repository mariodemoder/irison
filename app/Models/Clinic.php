<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Clinic extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'email', 'phone', 'address', 'timezone'
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
        return $this->hasMany(Pack::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function isTrialActive(): bool
    {
        if (! $this->trial_ends_at) return false;
        // $this->trial_ends_at is cast to Carbon by Eloquent
        return $this->trial_ends_at->isFuture();
    }

    public function isSubscribed(): bool
    {
        return ! is_null($this->subscribed_at);
    }
}
