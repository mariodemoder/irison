<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model
{
    use HasFactory;

    protected $table = 'billing_payments';

    protected $fillable = [
        'clinic_id', 'amount', 'currency', 'status', 'provider', 'provider_ref'
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
