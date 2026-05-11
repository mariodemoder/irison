<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MySaasCounter extends Model
{
    protected $table = 'counters_my_sass';

    protected $fillable = [
        'prefix',
        'last_number',
        'table_type',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];
}