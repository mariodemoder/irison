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
    SubscriptionRequest,
    EmailLog,
};

use App\Policies\{
    PatientPolicy,
    AppointmentPolicy,
    ClinicalRecordPolicy,
    PaymentPolicy,
    DocumentPolicy,
    ProductPolicy,
    ConsentCategoryPolicy,
    ConsentTemplatePolicy,
    PatientConsentPolicy,
    SubscriptionRequestPolicy,
    EmailLogPolicy,
};

use Modules\Notifications\Infrastructure\Policies\ReminderPolicy;

use Modules\Bonus\Models\BonusType as BonusTypeModel;
use Modules\Bonus\Policies\BonusPolicy;
use Modules\Bonus\Policies\BonusTypePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Patient::class        => PatientPolicy::class,
        Appointment::class    => AppointmentPolicy::class,
        ClinicalRecord::class => ClinicalRecordPolicy::class,
        Bonus::class          => BonusPolicy::class,
        BonusTypeModel::class => BonusTypePolicy::class,
        Payment::class        => PaymentPolicy::class,
        Reminder::class       => ReminderPolicy::class,
        Document::class       => DocumentPolicy::class,
        Product::class        => ProductPolicy::class,
        ConsentCategory::class => ConsentCategoryPolicy::class,
        ConsentTemplate::class => ConsentTemplatePolicy::class,
        PatientConsent::class  => PatientConsentPolicy::class,
        SubscriptionRequest::class => SubscriptionRequestPolicy::class,
        EmailLog::class         => EmailLogPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
