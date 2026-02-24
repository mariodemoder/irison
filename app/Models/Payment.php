<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToClinic;

class Payment extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'patient_id', 'appointment_id',
        'pack_id', 'amount', 'method', 'status', 'notes', 'paid_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }
}
