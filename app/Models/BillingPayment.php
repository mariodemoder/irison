<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Counters\MySaasCounterService;

class BillingPayment extends Model
{
    use HasFactory;

    protected $table = 'billing_payments';

    protected $fillable = [
        'clinic_id', 'amount', 'currency', 'status', 'provider', 'provider_ref', 'method', 'counter',
        'invoice_url', 'receipt_url', 'subscription_request_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (BillingPayment $billingPayment) {
            if (!empty($billingPayment->counter)) {
                return;
            }

            $billingPayment->counter = app(MySaasCounterService::class)->nextFormatted('billing_payments');
        });
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class);
    }
}
