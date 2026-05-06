<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestAppointmentsTodayTomorrowSeeder extends Seeder
{
    public function run(): void
    {
        $clinicId = 6;
        $patientIds = [1, 2];

        $now = now();

        DB::table('clinics')->updateOrInsert(
            ['id' => $clinicId],
            [
                'name' => 'Clinica Test #6',
                'legal_name' => 'Clinica Test #6 SL',
                'email' => 'clinic6@test.local',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        foreach ($patientIds as $patientId) {
            DB::table('patients')->updateOrInsert(
                ['id' => $patientId],
                [
                    'clinic_id' => $clinicId,
                    'first_name' => 'Paciente',
                    'last_name' => (string) $patientId,
                    'phone' => null,
                    'email' => "paciente{$patientId}@test.local",
                    'birth_date' => null,
                    'notes' => 'Paciente de test para fixtures de citas',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $clinic = Clinic::query()->findOrFail($clinicId);
        app()->instance('activeClinic', $clinic);

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $startRange = (clone $today)->startOfDay();
        $endRange = (clone $tomorrow)->endOfDay();

        $existingIds = Appointment::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->whereIn('patient_id', $patientIds)
            ->whereBetween('start_time', [$startRange, $endRange])
            ->pluck('id');

        if ($existingIds->isNotEmpty()) {
            Payment::withoutGlobalScopes()
                ->whereIn('appointment_id', $existingIds)
                ->where('concept', 'appointment')
                ->delete();

            Appointment::withoutGlobalScopes()
                ->whereIn('id', $existingIds)
                ->delete();
        }

        $this->seedDay($today, $clinicId, $patientIds);
        $this->seedDay($tomorrow, $clinicId, $patientIds);
    }

    /**
     * Genera 10 citas para un día:
     * - 5 pagadas (simple)
     * - 5 impagas (simple)
     */
    private function seedDay(Carbon $day, int $clinicId, array $patientIds): void
    {
        for ($index = 0; $index < 10; $index++) {
            $patientId = $patientIds[$index % count($patientIds)];
            $start = (clone $day)->setTime(8 + $index, 0, 0);

            $factory = Appointment::factory()
                ->forClinic($clinicId)
                ->forPatient($patientId)
                ->atStart($start)
                ->state([
                    'status' => 'scheduled',
                    'price' => 45,
                    'notes' => 'Fixture test dashboard',
                ]);

            if ($index < 5) {
                $factory->paidSimple()->create();
            } else {
                $factory->unpaidSimple()->create();
            }
        }
    }
}
