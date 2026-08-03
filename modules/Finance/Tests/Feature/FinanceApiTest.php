<?php

declare(strict_types=1);

namespace Modules\Finance\Tests\Feature;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $owner;
    private User $professional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureClinic::class,
            EnsureClinicIsActive::class,
            CheckSubscriptionAccess::class,
        ]);

        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Finance Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'max_users' => 5,
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->professional = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr Prof',
            'email' => 'prof@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'professional')->first()->id,
        ]);
    }

    private function seedProfiles(): void
    {
        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
            ['name' => 'Recepcionista', 'slug' => 'reception'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }

    private function actAsOwner(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);
    }

    public function test_expense_categories_crud(): void
    {
        $this->actAsOwner();

        $store = $this->postJson('/api/finance/expense-categories', [
            'name' => 'Alquiler',
            'color' => '#ef4444',
        ])->assertStatus(201);

        $categoryId = $store->json('data.id');

        $this->assertDatabaseHas('expense_categories', ['name' => 'Alquiler', 'clinic_id' => $this->clinic->id]);

        $this->getJson('/api/finance/expense-categories')->assertOk();

        $this->putJson('/api/finance/expense-categories/' . $categoryId, [
            'name' => 'Alquiler Local',
        ])->assertOk();

        $this->deleteJson('/api/finance/expense-categories/' . $categoryId)->assertOk();

        $this->assertDatabaseMissing('expense_categories', ['id' => $categoryId]);
    }

    public function test_expenses_crud(): void
    {
        $this->actAsOwner();

        $store = $this->postJson('/api/finance/expenses', [
            'concept' => 'Compra de material',
            'supplier' => 'Proveedor SL',
            'amount' => 100,
            'tax_rate' => 21,
            'date' => Carbon::now()->toDateString(),
            'payment_method' => 'transfer',
        ])->assertStatus(201);

        $expenseId = $store->json('data.id');

        $this->assertEquals(121.0, (float) $store->json('data.total'));

        $this->getJson('/api/finance/expenses')->assertOk();
        $this->getJson('/api/finance/expenses/' . $expenseId)->assertOk();

        $this->putJson('/api/finance/expenses/' . $expenseId, [
            'concept' => 'Compra de material (2)',
        ])->assertOk();

        $this->deleteJson('/api/finance/expenses/' . $expenseId)->assertOk();
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_professional_rate_save_and_list(): void
    {
        $this->actAsOwner();

        $this->putJson('/api/finance/professional-rates/' . $this->professional->id, [
            'cost_per_hour' => 35,
        ])->assertOk();

        $this->assertDatabaseHas('professional_rates', [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->professional->id,
            'cost_per_hour' => 35,
        ]);

        $response = $this->getJson('/api/finance/professional-rates')->assertOk();
        $rates = collect($response->json('data'));
        $this->assertTrue($rates->contains('user_id', $this->professional->id));

        $this->putJson('/api/finance/professional-rates/' . $this->professional->id, [
            'cost_per_hour' => 0,
        ])->assertOk();

        $this->assertDatabaseMissing('professional_rates', ['user_id' => $this->professional->id]);
    }

    public function test_benefits_report_computes_margin(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-100',
            'typeinvoice' => 'manual',
            'date' => Carbon::now()->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Consulta',
            'price' => 80,
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHour()->startOfHour(),
            'end_time' => Carbon::now()->subHour()->startOfHour()->addHour(),
            'price' => 80,
            'app_type_id' => $appointmentType->id,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->putJson('/api/finance/professional-rates/' . $this->professional->id, [
            'cost_per_hour' => 30,
        ])->assertOk();

        $this->postJson('/api/finance/expenses', [
            'concept' => 'Alquiler',
            'amount' => 20,
            'tax_rate' => 0,
            'date' => Carbon::now()->toDateString(),
        ])->assertStatus(201);

        $response = $this->getJson('/api/finance/benefits')->assertOk();

        $totals = $response->json('data.totals');

        $this->assertEquals(100.0, $totals['revenue']);
        $this->assertEquals(30.0, $totals['labor_cost']);
        $this->assertEquals(20.0, $totals['expenses']);
        $this->assertEquals(50.0, $totals['cost']);
        $this->assertEquals(50.0, $totals['profit']);
        $this->assertEquals(50.0, $totals['margin_percentage']);

        $this->assertNotEmpty($response->json('data.by_professional'));
        $this->assertNotEmpty($response->json('data.by_service'));
        $this->assertNotEmpty($response->json('data.by_category'));
    }

    public function test_professional_cannot_access_finance(): void
    {
        $this->actingAs($this->professional, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/finance/expenses')->assertForbidden();
        $this->getJson('/api/finance/benefits')->assertForbidden();
    }

    public function test_other_payments_are_included_in_benefits_revenue(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'professional_id' => $this->professional->id,
            'amount' => 300,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/finance/benefits')->assertOk();

        $this->assertEquals(300.0, (float) $response->json('data.totals.revenue'));

        $byProfessional = collect($response->json('data.by_professional'));
        $this->assertTrue($byProfessional->contains('user_id', $this->professional->id));
        $this->assertEquals(300.0, (float) $byProfessional->firstWhere('user_id', $this->professional->id)['revenue']);
    }

    public function test_benefits_report_includes_monthly_comparison_when_dates_provided(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Bea', 'last_name' => 'Diaz']);

        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = $currentMonth->copy()->subMonth();

        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-CURRENT',
            'typeinvoice' => 'manual',
            'date' => $currentMonth->toDateString(),
            'amount' => 200,
            'status' => 'issued',
        ]);

        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-PREV',
            'typeinvoice' => 'manual',
            'date' => $previousMonth->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        $from = $currentMonth->copy()->startOfMonth()->toDateString();
        $to = $currentMonth->copy()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/finance/benefits?from_date={$from}&to_date={$to}")->assertOk();

        $this->assertEquals(200.0, (float) $response->json('data.totals.revenue'));
        $this->assertEquals(100.0, (float) $response->json('data.previous_totals.revenue'));
        $this->assertEquals(100.0, (float) $response->json('data.variation.revenue'));
    }
}