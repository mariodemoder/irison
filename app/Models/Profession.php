<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profession extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
