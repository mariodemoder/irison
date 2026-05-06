<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = Carbon::now()->startOfHour()->addHours((int) fake()->numberBetween(1, 8));
        $end = (clone $start)->addHour();

        return [
            'clinic_id' => 1,
            'patient_id' => 1,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'notes' => null,
            'payment_type' => 'single',
            'bonus_id' => null,
        ];
    }

    public function forClinic(int $clinicId): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinicId,
        ]);
    }

    public function forPatient(int $patientId): static
    {
        return $this->state(fn () => [
            'patient_id' => $patientId,
        ]);
    }

    public function atStart(Carbon $start): static
    {
        return $this->state(fn () => [
            'start_time' => $start,
            'end_time' => (clone $start)->addHour(),
        ]);
    }

    public function unpaidSimple(): static
    {
        return $this->state(fn () => [
            'payment_type' => 'single',
            'payment_status' => 'pending',
        ]);
    }

    public function paidSimple(): static
    {
        return $this->state(fn () => [
            'payment_type' => 'single',
            'payment_status' => 'paid',
        ])->afterCreating(function (Appointment $appointment): void {
            $alreadyExists = Payment::query()
                ->where('appointment_id', $appointment->id)
                ->where('concept', 'appointment')
                ->exists();

            if ($alreadyExists) {
                return;
            }

            Payment::forceCreate([
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'package_id' => null,
                'concept' => 'appointment',
                'amount' => (float) ($appointment->price ?? 0),
                'method' => 'cash',
                'status' => 'completed',
                'notes' => 'Pago simple de prueba',
                'paid_at' => now(),
            ]);
        });
    }
}
