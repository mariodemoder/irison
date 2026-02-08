<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\Clinic;
use App\Models\Appointment;
use App\Models\Payment;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('es_ES');

        // Asegurar que exista una clínica para asignar
        $clinic = Clinic::first();
        if (! $clinic) {
            $clinic = Clinic::create([
                'name' => 'Clinica Demo',
                'legal_name' => 'Clinica Demo SL',
                'email' => 'demo@clinic.test'
            ]);
        }

        // Crear pacientes
        $count = 50;
        for ($i = 0; $i < $count; $i++) {
            $first = $faker->firstName();
            $last = $faker->lastName();

            $birth = $faker->optional(0.7)->dateTimeBetween('-80 years', '-5 years');

            $patient = Patient::create([
                'clinic_id' => $clinic->id,
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $faker->phoneNumber(),
                'email' => $faker->optional(0.8)->safeEmail(),
                'birth_date' => $birth ? $birth->format('Y-m-d') : null,
                'notes' => $faker->optional(0.4)->sentence(8),
            ]);

            // Para algunos pacientes crear citas y pagos para probar bloqueo de borrado
            if ($i < 10) {
                Appointment::create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'start_time' => now()->addDays(rand(1, 30)),
                    'end_time' => null,
                    'status' => 'scheduled',
                    'payment_status' => 'pending',
                ]);

                Payment::create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'appointment_id' => null,
                    'pack_id' => null,
                    'amount' => $faker->randomFloat(2, 20, 200),
                    'method' => 'card',
                    'status' => 'pending',
                ]);
            }
        }
    }
}
