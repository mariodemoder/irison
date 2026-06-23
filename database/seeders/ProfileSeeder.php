<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor',        'slug' => 'manager'],
            ['name' => 'Profesional',   'slug' => 'professional'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
