<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\Concerns\BelongsToClinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalRateEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'professional_rates';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'cost_per_hour',
    ];

    protected $casts = [
        'cost_per_hour' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}