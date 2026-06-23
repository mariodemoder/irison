<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Clinic;
use App\Models\Profile;
use App\Models\Profession;
use App\Models\UserSchedule;
use App\Models\UserScheduleException;
use App\Models\Booking\BookingProfessional;
use App\Notifications\ResetPasswordNotificationEs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClinic;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use SoftDeletes, BelongsToClinic;
    use HasApiTokens;

    protected $fillable = [
        'clinic_id', 'name', 'email', 'password', 'role',
        'profile_id', 'profession_id', 'allow_online_booking',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allow_online_booking' => 'boolean',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function profession()
    {
        return $this->belongsTo(Profession::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(UserSchedule::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(UserScheduleException::class);
    }

    public function bookingProfessional(): HasOne
    {
        return $this->hasOne(BookingProfessional::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotificationEs($token));
    }
}
