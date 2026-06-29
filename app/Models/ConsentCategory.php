<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConsentCategory extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (ConsentCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ConsentTemplate::class, 'category_id');
    }
}
