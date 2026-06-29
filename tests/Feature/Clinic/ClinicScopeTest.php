<?php

namespace Tests\Feature\Clinic;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Clinic;
use App\Models\Patient;

class ClinicScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_model_sets_clinic_id_and_scoping_applies()
    {
        $clinicA = Clinic::create(['name' => 'A Clinic', 'legal_name' => 'A', 'email' => 'a@a.test', 'phone' => '000', 'address' => 'x', 'timezone' => 'UTC']);
        $clinicB = Clinic::create(['name' => 'B Clinic', 'legal_name' => 'B', 'email' => 'b@b.test', 'phone' => '111', 'address' => 'y', 'timezone' => 'UTC']);

        app()->instance('clinic', $clinicA);

        // create patient without clinic_id, trait should fill it
        $p = Patient::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->assertEquals($clinicA->id, $p->clinic_id);
        $this->assertEquals('John Doe', $p->name);
        $this->assertArrayHasKey('name', $p->toArray());

        // create another patient in clinic B directly
        app()->instance('clinic', $clinicB);
        $pB = Patient::create(['first_name' => 'Jane', 'last_name' => 'Roe']);
        $this->assertEquals($clinicB->id, $pB->clinic_id);

        // when bound to clinicA, queries must only return A's patients
        app()->instance('clinic', $clinicA);
        $patients = Patient::all();
        $this->assertCount(1, $patients);
        $this->assertEquals('John', $patients->first()->first_name);
        $this->assertEquals('John Doe', $patients->first()->name);

        // try to update clinic B patient using a scoped query from clinic A
        $updated = Patient::where('id', $pB->id)->update(['first_name' => 'Hacked']);
        $this->assertEquals(0, $updated);
    }
}
