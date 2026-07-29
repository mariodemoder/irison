<?php

namespace Modules\Bonus\Tests\Unit;

use App\Models\Bonus;
use App\Models\BonusType;
use App\Models\BonusUsage;
use App\Models\Patient;
use App\Models\User;
use App\Models\Clinic;
use App\Models\AppointmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Bonus\Services\BonusService;
use Modules\Bonus\Models\BonusSessionLine;

class BonusServiceTest extends TestCase
{
    use RefreshDatabase;

    private BonusService $service;
    private Clinic $clinic;
    private User $user;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BonusService();

        $this->clinic = Clinic::create(['name' => 'Test Clinic']);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
        ]);
        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@example.com',
        ]);
    }

    public function test_create_bonus_manually(): void
    {
        $bonus = $this->service->createForPatient(
            $this->patient->id,
            [
                'name' => 'Bono QA',
                'total_sessions' => 5,
                'price' => 100,
            ],
            $this->clinic->id
        );

        $this->assertEquals('Bono QA', $bonus->name);
        $this->assertEquals(5, $bonus->total_sessions);
        $this->assertEquals(5, $bonus->remaining_sessions);
        $this->assertEquals(100, $bonus->price);
        $this->assertFalse($bonus->hasSessionLines());
    }

    public function test_create_bonus_from_template_creates_session_lines(): void
    {
        $aptType1 = AppointmentType::create(['clinic_id' => $this->clinic->id, 'name' => 'Fisioterapia']);
        $aptType2 = AppointmentType::create(['clinic_id' => $this->clinic->id, 'name' => 'Osteopatía']);

        $template = BonusType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Bono Fisio + Osteo',
            'sessions' => 8,
            'price' => 200,
        ]);

        $template->appointmentTypes()->attach($aptType1->id, ['quantity' => 5, 'unit_price' => 30]);
        $template->appointmentTypes()->attach($aptType2->id, ['quantity' => 3, 'unit_price' => 40]);

        $bonus = $this->service->createForPatient(
            $this->patient->id,
            [
                'name' => 'Bono Mixto',
                'total_sessions' => 8,
                'price' => 200,
                'bonus_type_id' => $template->id,
            ],
            $this->clinic->id
        );

        $this->assertTrue($bonus->hasSessionLines());
        $this->assertCount(2, $bonus->sessionLines);

        $line1 = $bonus->sessionLines->where('appointment_type_id', $aptType1->id)->first();
        $this->assertEquals(5, $line1->quantity);
        $this->assertEquals(5, $line1->remaining_quantity);

        $line2 = $bonus->sessionLines->where('appointment_type_id', $aptType2->id)->first();
        $this->assertEquals(3, $line2->quantity);
        $this->assertEquals(3, $line2->remaining_quantity);
    }

    public function test_delete_bonus_without_invoice(): void
    {
        $bonus = $this->service->createForPatient(
            $this->patient->id,
            ['name' => 'Bono Test', 'total_sessions' => 3],
            $this->clinic->id
        );

        $this->service->deleteBonus($bonus);

        $this->assertDatabaseMissing('bonuses', ['id' => $bonus->id]);
    }

    public function test_delete_bonus_with_invoice_throws(): void
    {
        $bonus = $this->service->createForPatient(
            $this->patient->id,
            ['name' => 'Bono Facturado', 'total_sessions' => 3],
            $this->clinic->id
        );

        $bonus->invoice_id = 1;
        $this->expectException(\DomainException::class);
        $this->service->deleteBonus($bonus);
    }

    public function test_for_patient_returns_session_lines(): void
    {
        $aptType = AppointmentType::create(['clinic_id' => $this->clinic->id, 'name' => 'Fisioterapia']);

        $template = BonusType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Bono Fisio',
            'sessions' => 5,
            'price' => 150,
        ]);

        $template->appointmentTypes()->attach($aptType->id, ['quantity' => 5, 'unit_price' => 30]);

        $bonus = $this->service->createForPatient(
            $this->patient->id,
            [
                'name' => 'Bono Fisio',
                'total_sessions' => 5,
                'price' => 150,
                'bonus_type_id' => $template->id,
            ],
            $this->clinic->id
        );

        $result = $this->service->forPatient($this->patient->id, $this->clinic->id);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('session_lines', $result->first());
        $this->assertCount(1, $result->first()['session_lines']);
    }
}
