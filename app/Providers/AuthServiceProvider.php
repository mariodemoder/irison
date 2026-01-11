<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\{
    Patient,
    Appointment,
    ClinicalRecord,
    Pack,
    Payment,
    Reminder
};

use App\Policies\{
    PatientPolicy,
    AppointmentPolicy,
    ClinicalRecordPolicy,
    PackPolicy,
    PaymentPolicy,
    ReminderPolicy
};

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Patient::class        => PatientPolicy::class,
        Appointment::class    => AppointmentPolicy::class,
        ClinicalRecord::class => ClinicalRecordPolicy::class,
        Pack::class           => PackPolicy::class,
        Payment::class        => PaymentPolicy::class,
        Reminder::class       => ReminderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
