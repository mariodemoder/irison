<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryEloquentModel extends Model
{
    use BelongsToClinic;

    protected $table = 'expense_categories';

    protected $fillable = [
        'clinic_id',
        'name',
        'color',
        'description',
    ];
}