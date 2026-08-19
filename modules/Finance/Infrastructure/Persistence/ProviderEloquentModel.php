<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'providers';

    protected $fillable = [
        'clinic_id',
        'name',
        'nif',
        'email',
        'phone',
        'address',
        'notes',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(ExpenseEloquentModel::class, 'provider_id');
    }
}
