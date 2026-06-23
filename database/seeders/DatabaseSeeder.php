<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Asegurar que exista una clínica antes de crear el usuario
        $clinic = \App\Models\Clinic::first();
        if (! $clinic) {
            $clinic = \App\Models\Clinic::create([
                'name' => 'Clinica Demo',
                'legal_name' => 'Clinica Demo SL',
                'email' => 'demo@clinic.test'
            ]);
        }

        // Crear usuario de prueba sin usar la factory para evitar columnas ausentes
        if (! \App\Models\User::where('email', 'test@example.com')->exists()) {
            \App\Models\User::create([
                'clinic_id' => $clinic->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $this->call(ProfileSeeder::class);

        // Pacientes de prueba
        $this->call(PatientSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(TestAppointmentsTodayTomorrowSeeder::class);
        }
    }
}
