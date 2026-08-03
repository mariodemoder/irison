<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\Concerns\BelongsToClinic;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'expenses';

    protected $fillable = [
        'clinic_id',
        'category_id',
        'concept',
        'supplier',
        'amount',
        'tax_rate',
        'total',
        'date',
        'payment_method',
        'receipt_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
        'date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategoryEloquentModel::class, 'category_id');
    }

    public function getDateAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}