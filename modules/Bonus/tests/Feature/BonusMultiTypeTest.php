<?php

namespace Modules\Bonus\Tests\Feature;

use App\Models\AppointmentType;
use App\Models\Bonus;
use App\Models\BonusType;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Bonus\Models\BonusSessionLine;

class BonusMultiTypeTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private Patient $patient;
    private AppointmentType $fisioType;
    private AppointmentType $osteoType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Test Clinic']);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
        ]);

        app()->instance('activeClinic', $this->clinic);

        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@example.com',
        ]);
        $this->fisioType = AppointmentType::create(['clinic_id' => $this->clinic->id, 'name' => 'Fisioterapia']);
        $this->osteoType = AppointmentType::create(['clinic_id' => $this->clinic->id, 'name' => 'Osteopatía']);
    }

    private function createBonusWithSessionLines(): Bonus
    {
        $template = BonusType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Bono Mixto',
            'sessions' => 8,
            'price' => 200,
        ]);

        $template->appointmentTypes()->attach($this->fisioType->id, ['quantity' => 5, 'unit_price' => 30]);
        $template->appointmentTypes()->attach($this->osteoType->id, ['quantity' => 3, 'unit_price' => 40]);

        $bonus = Bonus::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'bonus_type_id' => $template->id,
            'name' => 'Bono Mixto',
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'price' => 200,
        ]);

        BonusSessionLine::create([
            'clinic_id' => $this->clinic->id,
            'bonus_id' => $bonus->id,
            'appointment_type_id' => $this->fisioType->id,
            'quantity' => 5,
            'remaining_quantity' => 5,
        ]);

        BonusSessionLine::create([
            'clinic_id' => $this->clinic->id,
            'bonus_id' => $bonus->id,
            'appointment_type_id' => $this->osteoType->id,
            'quantity' => 3,
            'remaining_quantity' => 3,
        ]);

        return $bonus;
    }

    public function test_bonus_expiring_endpoint(): void
    {
        $bonus = $this->createBonusWithSessionLines();
        $bonus->update(['remaining_sessions' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/bonuses/expiring');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_bonus_index_with_filters(): void
    {
        $this->createBonusWithSessionLines();

        $response = $this->actingAs($this->user)
            ->getJson('/api/bonuses');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta', 'summary']);
    }
}
