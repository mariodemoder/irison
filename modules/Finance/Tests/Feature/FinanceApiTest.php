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
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;
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

    public function test_benefits_report_includes_new_kpis(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'Patient']);

        // Create two completed payments with different methods
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 100,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 200,
            'method' => 'card',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/finance/benefits')->assertOk();

        // Verify paid_operations_count
        $this->assertEquals(2, $response->json('data.totals.paid_operations_count'));

        // Verify revenue_by_payment_method
        $methods = collect($response->json('data.revenue_by_payment_method'));
        $this->assertCount(2, $methods);

        $cashMethod = $methods->firstWhere('method', 'cash');
        $this->assertNotNull($cashMethod);
        $this->assertEquals(100.0, $cashMethod['total']);
        $this->assertEquals(1, $cashMethod['count']);

        $cardMethod = $methods->firstWhere('method', 'card');
        $this->assertNotNull($cardMethod);
        $this->assertEquals(200.0, $cardMethod['total']);
        $this->assertEquals(1, $cardMethod['count']);
    }

    public function test_list_pending_payments(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Juan', 'last_name' => 'Perez']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Fisioterapia',
            'price' => 60,
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHour()->startOfHour(),
            'end_time' => Carbon::now()->subHour()->startOfHour()->addHour(),
            'price' => 60,
            'app_type_id' => $appointmentType->id,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/finance/pending-payments')->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($appointment->id, $data[0]['appointment_id']);
        $this->assertEquals(60.0, $data[0]['price']);
        $this->assertEquals(0.0, $data[0]['paid_amount']);
        $this->assertEquals(60.0, $data[0]['pending_amount']);
        $this->assertEquals('pending', $data[0]['payment_status']);

        $summary = $response->json('summary');
        $this->assertEquals(1, $summary['count']);
        $this->assertEquals(60.0, $summary['total_pending_amount']);
    }

    public function test_register_payment_from_pending(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Masaje',
            'price' => 80,
        ]);

        $appointment = Appointment::create([
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

        $this->postJson("/api/finance/pending-payments/{$appointment->id}/register-payment", [
            'amount' => 50,
            'method' => 'cash',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $appointment->id,
            'amount' => 50,
            'method' => 'cash',
            'status' => 'completed',
            'concept' => 'appointment',
        ]);

        $appointment->refresh();
        $this->assertEquals('partially_paid', $appointment->payment_status);
    }

    public function test_register_full_payment_from_pending(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Bea', 'last_name' => 'Diaz']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Osteopatia',
            'price' => 70,
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHour()->startOfHour(),
            'end_time' => Carbon::now()->subHour()->startOfHour()->addHour(),
            'price' => 70,
            'app_type_id' => $appointmentType->id,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->postJson("/api/finance/pending-payments/{$appointment->id}/register-payment", [
            'amount' => 70,
            'method' => 'card',
        ])->assertOk();

        $appointment->refresh();
        $this->assertEquals('paid', $appointment->payment_status);
    }

    public function test_cannot_overpay_pending(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Carl', 'last_name' => 'Garcia']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Consulta',
            'price' => 50,
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHour()->startOfHour(),
            'end_time' => Carbon::now()->subHour()->startOfHour()->addHour(),
            'price' => 50,
            'app_type_id' => $appointmentType->id,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->postJson("/api/finance/pending-payments/{$appointment->id}/register-payment", [
            'amount' => 100,
            'method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_pending_payments_excludes_canceled(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Dan', 'last_name' => 'Torres']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Pilates',
            'price' => 40,
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHour()->startOfHour(),
            'end_time' => Carbon::now()->subHour()->startOfHour()->addHour(),
            'price' => 40,
            'app_type_id' => $appointmentType->id,
            'status' => 'canceled',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/finance/pending-payments')->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ── Providers ──

    public function test_provider_crud(): void
    {
        $this->actAsOwner();

        // Create
        $response = $this->postJson('/api/finance/providers', [
            'name' => 'Proveedor ABC',
            'nif' => 'B12345678',
            'email' => 'abc@proveedor.com',
            'phone' => '912345678',
            'address' => 'Calle Mayor 1',
        ])->assertCreated();

        $providerId = $response->json('data.id');
        $this->assertEquals('Proveedor ABC', $response->json('data.name'));
        $this->assertEquals('B12345678', $response->json('data.nif'));

        // List
        $response = $this->getJson('/api/finance/providers')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Proveedor ABC', $response->json('data.0.name'));

        // Update
        $this->putJson("/api/finance/providers/{$providerId}", [
            'name' => 'Proveedor ABC S.L.',
            'nif' => 'B87654321',
        ])->assertOk();

        $response = $this->getJson('/api/finance/providers')->assertOk();
        $this->assertEquals('Proveedor ABC S.L.', $response->json('data.0.name'));
        $this->assertEquals('B87654321', $response->json('data.0.nif'));

        // Delete
        $this->deleteJson("/api/finance/providers/{$providerId}")->assertOk();

        $response = $this->getJson('/api/finance/providers')->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_provider_unique_name_per_clinic(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/finance/providers', ['name' => 'Proveedor X'])->assertCreated();
        $this->postJson('/api/finance/providers', ['name' => 'Proveedor X'])->assertStatus(422);
    }

    public function test_expense_with_provider(): void
    {
        $this->actAsOwner();

        $provider = $this->createProvider('Proveedor Test');

        $category = \Modules\Finance\Infrastructure\Persistence\ExpenseCategoryEloquentModel::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Suministros',
        ]);

        $response = $this->postJson('/api/finance/expenses', [
            'concept' => 'Compra material',
            'amount' => 100,
            'tax_rate' => 21,
            'provider_id' => $provider->id,
            'category_id' => $category->id,
        ])->assertCreated();

        $this->assertEquals($provider->id, $response->json('data.provider_id'));

        // Verify provider is included in list response
        $response = $this->getJson('/api/finance/expenses')->assertOk();
        $this->assertEquals('Proveedor Test', $response->json('data.0.provider.name'));
    }

    public function test_expenses_filter_by_provider(): void
    {
        $this->actAsOwner();

        $p1 = $this->createProvider('Proveedor Alpha');
        $p2 = $this->createProvider('Proveedor Beta');

        // Create expense with provider 1
        $this->postJson('/api/finance/expenses', [
            'concept' => 'Gasto P1',
            'amount' => 50,
            'provider_id' => $p1->id,
        ])->assertCreated();

        // Create expense with provider 2
        $this->postJson('/api/finance/expenses', [
            'concept' => 'Gasto P2',
            'amount' => 75,
            'provider_id' => $p2->id,
        ])->assertCreated();

        // Filter by provider 1
        $response = $this->getJson('/api/finance/expenses?provider_id=' . $p1->id)->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Gasto P1', $response->json('data.0.concept'));
    }

    // ── Income & Refunds (Fase 3) ──

    public function test_list_income_payments(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        // Create a completed payment
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'concept' => 'appointment',
            'amount' => 60,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        // Create a manual payment
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 25,
            'method' => 'card',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/finance/income')->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_register_manual_income(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Test']);

        $response = $this->postJson('/api/finance/income', [
            'amount' => 100,
            'method' => 'transfer',
            'description' => 'Curso de formación',
            'patient_id' => $patient->id,
            'date' => Carbon::now()->format('Y-m-d'),
        ])->assertCreated();

        $this->assertEquals('other', $response->json('data.concept'));
        $this->assertEquals(100, $response->json('data.amount'));
        $this->assertEquals('completed', $response->json('data.status'));

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $this->clinic->id,
            'concept' => 'other',
            'amount' => 100,
            'method' => 'transfer',
            'status' => 'completed',
        ]);
    }

    public function test_refund_payment(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Bea', 'last_name' => 'Garcia']);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'concept' => 'appointment',
            'amount' => 50,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->postJson("/api/finance/payments/{$payment->id}/refund", [
            'reason' => 'Paciente solicita devolución',
        ])->assertOk();

        $this->assertEquals('refunded', $response->json('data.payment.status'));
        $this->assertNotNull($response->json('data.payment.refunded_at'));
        $this->assertEquals('Paciente solicita devolución', $response->json('data.payment.refund_reason'));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
            'refund_reason' => 'Paciente solicita devolución',
        ]);
    }

    public function test_cannot_refund_twice(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Carl', 'last_name' => 'Ruiz']);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 30,
            'method' => 'card',
            'status' => 'refunded',
            'refund_reason' => 'Ya reembolsado',
            'refunded_at' => Carbon::now(),
            'paid_at' => Carbon::now(),
        ]);

        $this->postJson("/api/finance/payments/{$payment->id}/refund", [
            'reason' => 'Segundo intento',
        ])->assertStatus(403);
    }

    public function test_refund_with_abono(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Diana', 'last_name' => 'Torres']);

        $appointmentType = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Osteopatia',
            'price' => 80,
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subDay(),
            'end_time' => Carbon::now()->subDay()->addHour(),
            'price' => 80,
            'app_type_id' => $appointmentType->id,
            'status' => 'scheduled',
            'payment_status' => 'paid',
        ]);

        // Create an invoice for the appointment
        $invoice = Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => Document::TYPE_INVOICE,
            'type_from' => 'appointment',
            'from_id' => $appointment->id,
            'typeinvoice' => 'appointment',
            'amount' => 80,
            'date' => Carbon::now()->subDay(),
            'status' => 'issued',
            'clinic_name' => $this->clinic->name,
            'clinic_nif' => 'B00000000',
            'clinic_address' => 'Calle Test',
            'clinic_zip' => '28000',
            'clinic_province' => 'Madrid',
            'clinic_country' => 'España',
            'user_full_name' => $this->owner->name,
            'patient_nif' => '12345678Z',
            'patient_full_name' => 'Diana Torres',
        ]);

        // Link invoice to appointment
        $appointment->update(['invoice_id' => $invoice->id]);

        // Create a payment for the appointment
        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'appointment_id' => $appointment->id,
            'concept' => 'appointment',
            'amount' => 80,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        // Refund with abono generation
        $response = $this->postJson("/api/finance/payments/{$payment->id}/refund", [
            'reason' => 'Servicio no realizado',
            'generate_abono' => true,
        ])->assertOk();

        $this->assertEquals('refunded', $response->json('data.payment.status'));
        $this->assertTrue($response->json('data.abono.created'));
        $this->assertNotNull($response->json('data.abono.document_id'));

        // Verify abono was created
        $this->assertDatabaseHas('documents', [
            'clinic_id' => $this->clinic->id,
            'type' => Document::TYPE_ABONO,
            'type_from' => Document::TYPE_INVOICE,
            'from_id' => $invoice->id,
        ]);
    }

    public function test_refund_without_invoice_skips_abono(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Eva', 'last_name' => 'Rios']);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 40,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        // Refund with abono flag but no appointment/invoice
        $response = $this->postJson("/api/finance/payments/{$payment->id}/refund", [
            'reason' => 'Error de cobro',
            'generate_abono' => true,
        ])->assertOk();

        $this->assertEquals('refunded', $response->json('data.payment.status'));
        $this->assertFalse($response->json('data.abono.created'));
        $this->assertEquals('no_invoice', $response->json('data.abono.reason'));
    }

    public function test_finance_summary_returns_all_fields(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/finance/summary')->assertOk();

        $this->assertArrayHasKey('current_period', $response->json('data'));
        $this->assertArrayHasKey('previous_period', $response->json('data'));
        $this->assertArrayHasKey('variation', $response->json('data'));
        $this->assertArrayHasKey('evolution', $response->json('data'));
        $this->assertArrayHasKey('by_payment_method', $response->json('data'));

        $current = $response->json('data.current_period');
        $this->assertArrayHasKey('revenue', $current);
        $this->assertArrayHasKey('expenses', $current);
        $this->assertArrayHasKey('labor_cost', $current);
        $this->assertArrayHasKey('profit', $current);
        $this->assertArrayHasKey('margin_percentage', $current);
        $this->assertArrayHasKey('ticket_medio', $current);
        $this->assertArrayHasKey('paid_operations_count', $current);
        $this->assertArrayHasKey('pending_count', $current);
        $this->assertArrayHasKey('pending_amount', $current);
    }

    public function test_finance_summary_evolution_has_up_to_12_records(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/finance/summary')->assertOk();

        $evolution = $response->json('data.evolution');
        $this->assertIsArray($evolution);
        $this->assertLessThanOrEqual(12, count($evolution));

        foreach ($evolution as $record) {
            $this->assertArrayHasKey('month', $record);
            $this->assertArrayHasKey('revenue', $record);
            $this->assertArrayHasKey('expenses', $record);
            $this->assertArrayHasKey('profit', $record);
        }
    }

    public function test_finance_summary_variations_are_numeric(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/finance/summary')->assertOk();

        $variation = $response->json('data.variation');
        $this->assertIsArray($variation);

        foreach (['revenue', 'expenses', 'profit', 'margin_percentage'] as $key) {
            $this->assertArrayHasKey($key, $variation);
            $value = $variation[$key];
            $this->assertTrue(
                $value === null || is_numeric($value),
                "Variation '{$key}' should be numeric or null, got: " . var_export($value, true),
            );
        }
    }

    // ──────────────────────────────────────────────────────
    // Fase 5: Informes Exportables
    // ──────────────────────────────────────────────────────

    public function test_income_report(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-200',
            'typeinvoice' => 'manual',
            'date' => Carbon::now()->toDateString(),
            'amount' => 150,
            'status' => 'issued',
        ]);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 40,
            'method' => 'card',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/finance/reports/income')->assertOk();
        $data = $response->json('data');

        $this->assertEquals('income', $data['type']);
        $this->assertIsArray($data['headers']);
        $this->assertIsArray($data['rows']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('total', $data['summary']);
        $this->assertGreaterThan(0, count($data['rows']));
    }

    public function test_expenses_report(): void
    {
        $this->actAsOwner();

        $category = \Modules\Finance\Infrastructure\Persistence\ExpenseCategoryEloquentModel::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Alquiler',
            'color' => '#ef4444',
        ]);

        ExpenseEloquentModel::create([
            'clinic_id' => $this->clinic->id,
            'category_id' => $category->id,
            'concept' => 'Alquiler agosto',
            'amount' => 800,
            'tax_rate' => 21,
            'total' => 968,
            'date' => Carbon::now()->toDateString(),
            'payment_method' => 'transfer',
        ]);

        $response = $this->getJson('/api/finance/reports/expenses')->assertOk();
        $data = $response->json('data');

        $this->assertEquals('expenses', $data['type']);
        $this->assertIsArray($data['headers']);
        $this->assertIsArray($data['rows']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(968, $data['summary']['total']);
    }

    public function test_profit_report(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Pedro', 'last_name' => 'Garcia']);

        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-300',
            'typeinvoice' => 'manual',
            'date' => Carbon::now()->toDateString(),
            'amount' => 200,
            'status' => 'issued',
        ]);

        ExpenseEloquentModel::create([
            'clinic_id' => $this->clinic->id,
            'concept' => 'Material',
            'amount' => 50,
            'tax_rate' => 21,
            'total' => 60.50,
            'date' => Carbon::now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson('/api/finance/reports/profit')->assertOk();
        $data = $response->json('data');

        $this->assertEquals('profit', $data['type']);
        $this->assertIsArray($data['headers']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('total_revenue', $data['summary']);
        $this->assertArrayHasKey('total_expenses', $data['summary']);
        $this->assertArrayHasKey('total_profit', $data['summary']);
        $this->assertEquals(139.50, $data['summary']['total_profit']);
    }

    public function test_professional_report(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Luis', 'last_name' => 'Martinez']);

        $at = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Masaje',
            'price' => 70,
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => Carbon::now()->subHour(),
            'price' => 70,
            'app_type_id' => $at->id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $response = $this->getJson('/api/finance/reports/professional')->assertOk();
        $data = $response->json('data');

        $this->assertEquals('professional', $data['type']);
        $this->assertIsArray($data['headers']);
        $this->assertIsArray($data['rows']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('total_revenue', $data['summary']);
        $this->assertGreaterThan(0, count($data['rows']));
    }

    public function test_service_report(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Maria', 'last_name' => 'Sanchez']);

        $at = AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Fisioterapia',
            'price' => 90,
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::now()->subHours(3),
            'end_time' => Carbon::now()->subHours(2),
            'price' => 90,
            'app_type_id' => $at->id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $response = $this->getJson('/api/finance/reports/service')->assertOk();
        $data = $response->json('data');

        $this->assertEquals('service', $data['type']);
        $this->assertIsArray($data['headers']);
        $this->assertIsArray($data['rows']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(90, $data['summary']['total_revenue']);
    }

    public function test_csv_export_returns_csv(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/finance/reports/income/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Período', $content);
    }

    public function test_report_with_dates_filters_correctly(): void
    {
        $this->actAsOwner();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Filtro', 'last_name' => 'Test']);

        // Invoice today
        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'INV-500',
            'typeinvoice' => 'manual',
            'date' => Carbon::now()->toDateString(),
            'amount' => 200,
            'status' => 'issued',
        ]);

        // Without date filter — should find the invoice
        $response = $this->getJson('/api/finance/reports/income')->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data['rows']), 'Rows should not be empty');

        // With date filter — should only include today
        $response = $this->getJson('/api/finance/reports/income?' . http_build_query([
            'from_date' => Carbon::now()->subDays(5)->toDateString(),
            'to_date' => Carbon::now()->addDays(5)->toDateString(),
        ]))->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data['rows']), 'Rows should not be empty with date filter');
    }

    private function createProvider(string $name): object
    {
        return \Modules\Finance\Infrastructure\Persistence\ProviderEloquentModel::create([
            'clinic_id' => $this->clinic->id,
            'name' => $name,
        ]);
    }
}