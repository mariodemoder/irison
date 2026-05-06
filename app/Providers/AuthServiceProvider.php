<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\{
    Patient,
    Appointment,
    ClinicalRecord,
    Bonus,
    Payment,
    Reminder,
    Document,
    Product
};

use App\Policies\{
    PatientPolicy,
    AppointmentPolicy,
    ClinicalRecordPolicy,
    PackPolicy,
    PaymentPolicy,
    ReminderPolicy,
    DocumentPolicy,
    ProductPolicy
};

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Patient::class        => PatientPolicy::class,
        Appointment::class    => AppointmentPolicy::class,
        ClinicalRecord::class => ClinicalRecordPolicy::class,
        Bonus::class          => PackPolicy::class,
        Payment::class        => PaymentPolicy::class,
        Reminder::class       => ReminderPolicy::class,
        Document::class       => DocumentPolicy::class,
        Product::class        => ProductPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
