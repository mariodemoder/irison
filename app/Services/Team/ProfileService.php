<?php

namespace App\Services\Team;

use App\Models\Profile;

class ProfileService
{
    public function index(): array
    {
        return [
            'data' => Profile::orderBy('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])->toArray(),
        ];
    }
}
