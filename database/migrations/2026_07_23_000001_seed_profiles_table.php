<?php

declare(strict_types=1);

use App\Models\Profile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $profiles = [
        ['name' => 'Administrador', 'slug' => 'admin'],
        ['name' => 'Gestor',        'slug' => 'manager'],
        ['name' => 'Profesional',   'slug' => 'professional'],
        ['name' => 'Recepcionista', 'slug' => 'reception'],
    ];

    public function up(): void
    {
        foreach ($this->profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }

    public function down(): void
    {
        Profile::whereIn('slug', array_column($this->profiles, 'slug'))->delete();
    }
};
