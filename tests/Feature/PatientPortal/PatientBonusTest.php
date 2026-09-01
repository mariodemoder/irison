<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use Modules\Bonus\Models\BonusSessionLine;

class PatientBonusTest extends PatientPortalTestCase
{
    public function test_index_returns_own_bonuses(): void
    {
        $this->makeBonus($this->patient, ['name' => 'Pack A']);
        $this->makeBonus($this->patient, ['name' => 'Pack B']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/bonuses');

        $response->assertOk()
            ->assertJsonCount(2, 'bonuses');
    }

    public function test_index_excludes_foreign_clinic_bonuses(): void
    {
        $this->makeBonus($this->patient, ['name' => 'Pack Propio']);
        $this->makeBonus($this->foreignPatient, ['name' => 'Pack Ajeno']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/bonuses');

        $response->assertOk()
            ->assertJsonCount(1, 'bonuses')
            ->assertJsonFragment(['name' => 'Pack Propio'])
            ->assertJsonMissing(['name' => 'Pack Ajeno']);
    }

    public function test_show_returns_own_bonus_with_computed_status(): void
    {
        $bonus = $this->makeBonus($this->patient, ['remaining_sessions' => 1]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/bonuses/{$bonus->id}");

        $response->assertOk()
            ->assertJsonPath('bonus.id', $bonus->id)
            ->assertJsonPath('bonus.status', 'last');
    }

    public function test_show_includes_session_lines_for_multi_type_bonus(): void
    {
        $bonus = $this->makeBonus($this->patient, ['remaining_sessions' => 3]);
        $appointmentType = $this->makeAppointmentType($this->clinic);

        BonusSessionLine::create([
            'clinic_id' => $this->clinic->id,
            'bonus_id' => $bonus->id,
            'appointment_type_id' => $appointmentType->id,
            'quantity' => 3,
            'remaining_quantity' => 3,
        ]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/bonuses/{$bonus->id}");

        $response->assertOk()
            ->assertJsonPath('bonus.session_lines.0.remaining_quantity', 3);
    }

    public function test_show_other_patient_bonus_returns_404(): void
    {
        $bonus = $this->makeBonus($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/bonuses/{$bonus->id}")
            ->assertNotFound();
    }

    public function test_index_orders_by_created_at_desc(): void
    {
        $first = $this->makeBonus($this->patient, ['name' => 'Primero']);
        $second = $this->makeBonus($this->patient, ['name' => 'Segundo']);

        // Set precise timestamps through the query builder (no model casts).
        \App\Models\Bonus::where('id', $first->id)->update(['created_at' => '2020-01-01 09:00:00']);
        \App\Models\Bonus::where('id', $second->id)->update(['created_at' => '2021-01-01 09:00:00']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/bonuses');

        $response->assertOk()
            ->assertJsonPath('bonuses.0.name', 'Segundo')
            ->assertJsonPath('bonuses.1.name', 'Primero');
    }
}