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

use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;
use Modules\Finance\Infrastructure\Persistence\ExpenseCategoryEloquentModel;
use Modules\Finance\Infrastructure\Persistence\ProfessionalRateEloquentModel;
use Modules\Finance\Infrastructure\Persistence\ProviderEloquentModel;
use Modules\Finance\Infrastructure\Policies\ExpensePolicy;
use Modules\Finance\Infrastructure\Policies\ExpenseCategoryPolicy;
use Modules\Finance\Infrastructure\Policies\ProfessionalRatePolicy;
use Modules\Finance\Infrastructure\Policies\ProviderPolicy;

use Modules\Activity\Infrastructure\Persistence\ActivityLogQueryModel;
use Modules\Activity\Infrastructure\Policies\ActivityPolicy;

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
        ExpenseEloquentModel::class => ExpensePolicy::class,
        ExpenseCategoryEloquentModel::class => ExpenseCategoryPolicy::class,
        ProfessionalRateEloquentModel::class => ProfessionalRatePolicy::class,
        ProviderEloquentModel::class => ProviderPolicy::class,
        ActivityLogQueryModel::class => ActivityPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
