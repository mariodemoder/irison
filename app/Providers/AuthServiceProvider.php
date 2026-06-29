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
    Product,
    ConsentCategory,
    ConsentTemplate,
    PatientConsent,
};

use App\Policies\{
    PatientPolicy,
    AppointmentPolicy,
    ClinicalRecordPolicy,
    PackPolicy,
    PaymentPolicy,
    ReminderPolicy,
    DocumentPolicy,
    ProductPolicy,
    ConsentCategoryPolicy,
    ConsentTemplatePolicy,
    PatientConsentPolicy,
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
        ConsentCategory::class => ConsentCategoryPolicy::class,
        ConsentTemplate::class => ConsentTemplatePolicy::class,
        PatientConsent::class  => PatientConsentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
