<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\{
    Patient,
    Appointment,
    ClinicalRecord,
    Pack,
    PatientPayment,
    Reminder
};

use App\Policies\{
    PatientPolicy,
    AppointmentPolicy,
    ClinicalRecordPolicy,
    PackPolicy,
    PatientPaymentPolicy,
    ReminderPolicy
};

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Patient::class        => PatientPolicy::class,
        Appointment::class    => AppointmentPolicy::class,
        ClinicalRecord::class => ClinicalRecordPolicy::class,
        Pack::class           => PackPolicy::class,
        PatientPayment::class        => PatientPaymentPolicy::class,
        Reminder::class       => ReminderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
