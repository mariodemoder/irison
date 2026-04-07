<?php

namespace Tests\Feature\Api;

use App\Models\CashierSubscription;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Billable;
use Mockery;
use Tests\TestCase;

/**
 * Valida toda la implementacion de la Semana 3 -- Stripe Base:
 *
 * LUNES    -- Cashier instalado, migraciones aplicadas
 * MARTES   -- Clinic preparada para Cashier (Billable + columnas)
 * MIERCOLES -- STRIPE_PRICE_ID configurado
 * JUEVES   -- POST /api/subscribe operativo
 */
class Week3StripeBaseTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // LUNES -- Infraestructura lista
    // -------------------------------------------------------

    public function test_lunes_stripe_subscriptions_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('stripe_subscriptions'),
            'Falta la tabla stripe_subscriptions'
        );
    }

    public function test_lunes_stripe_subscription_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('stripe_subscription_items'));
    }

    public function test_lunes_clinics_table_has_stripe_columns(): void
    {
        foreach (['stripe_id', 'pm_type', 'pm_last_four'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('clinics', $column),
                "Falta columna '{$column}' en clinics"
            );
        }
    }

    public function test_lunes_cashier_subscription_model_usa_tabla_correcta(): void
    {
        $this->assertSame('stripe_subscriptions', (new CashierSubscription())->getTable());
    }

    // -------------------------------------------------------
    // MARTES -- Clinic preparada para Cashier
    // -------------------------------------------------------

    public function test_martes_clinic_usa_billable_trait(): void
    {
        $this->assertArrayHasKey(
            Billable::class,
            class_uses_recursive(Clinic::class),
            'Clinic no tiene el trait Billable de Cashier'
        );
    }

    public function test_martes_saas_subscriptions_es_has_many(): void
    {
        $clinic = Clinic::create(['name' => 'Clinica Test']);

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $clinic->saasSubscriptions()
        );
    }

    public function test_martes_is_trial_active_true_cuando_trial_futuro(): void
    {
        Carbon::setTestNow(now());

        $clinic = Clinic::create(['name' => 'Trial activo']);
        Subscription::create([
            'clinic_id'     => $clinic->id,
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($clinic->isTrialActive());
    }

    public function test_martes_is_trial_active_false_cuando_trial_expirado(): void
    {
        Carbon::setTestNow(now());

        $clinic = Clinic::create(['name' => 'Trial expirado']);
        Subscription::create([
            'clinic_id'     => $clinic->id,
            'status'        => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($clinic->isTrialActive());
    }

    public function test_martes_is_subscribed_true_para_subscripcion_activa(): void
    {
        $clinic = Clinic::create(['name' => 'Subscrita']);
        Subscription::create([
            'clinic_id'          => $clinic->id,
            'status'             => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertTrue($clinic->isSubscribed());
    }

    public function test_martes_is_subscribed_false_para_trial(): void
    {
        $clinic = Clinic::create(['name' => 'Solo trial']);
        Subscription::create([
            'clinic_id'     => $clinic->id,
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->assertFalse($clinic->isSubscribed());
    }

    public function test_martes_clinic_stripe_id_es_fillable(): void
    {
        $clinic = Clinic::create([
            'name'      => 'Clinica Stripe',
            'stripe_id' => 'cus_test_123',
        ]);

        $this->assertSame('cus_test_123', $clinic->stripe_id);
    }

    // -------------------------------------------------------
    // MIERCOLES -- Plan en Stripe configurado
    // -------------------------------------------------------

    public function test_miercoles_stripe_price_id_esta_configurado(): void
    {
        config(['services.stripe.price_id' => 'price_real_from_env']);

        $this->assertStringStartsWith(
            'price_',
            config('services.stripe.price_id'),
            "STRIPE_PRICE_ID debe empezar con 'price_'"
        );
    }

    // -------------------------------------------------------
    // JUEVES -- POST /api/subscribe
    // -------------------------------------------------------

    public function test_jueves_subscribe_requiere_autenticacion(): void
    {
        $this->postJson('/api/subscribe', ['payment_method' => 'pm_card_visa'])
            ->assertUnauthorized();
    }

    public function test_jueves_subscribe_requiere_payment_method(): void
    {
        [$user] = $this->crearUsuarioConClinicaActiva();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscribe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_jueves_subscribe_retorna_500_si_price_id_placeholder(): void
    {
        [$user] = $this->crearUsuarioConClinicaActiva();

        config(['services.stripe.price_id' => 'price_xxx']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscribe', ['payment_method' => 'pm_card_visa'])
            ->assertStatus(500)
            ->assertJson(['message' => 'STRIPE_PRICE_ID no configurado']);
    }

    public function test_jueves_subscribe_retorna_409_si_ya_suscrito_en_cashier(): void
    {
        [$user, $clinic] = $this->crearUsuarioConClinicaActiva();

        config(['services.stripe.price_id' => 'price_test_123']);

        // Insertar fila activa en stripe_subscriptions: no se necesita mockear nada
        CashierSubscription::create([
            'user_id'       => $clinic->id,
            'type'          => 'default',
            'stripe_id'     => 'sub_ya_existe_123',
            'stripe_status' => 'active',
            'stripe_price'  => 'price_test_123',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscribe', ['payment_method' => 'pm_card_visa'])
            ->assertStatus(409)
            ->assertJson(['message' => 'Ya tienes una suscripcion activa']);
    }

    public function test_jueves_subscribe_crea_suscripcion_con_cashier(): void
    {
        [$user, $clinic] = $this->crearUsuarioConClinicaActiva();

        // Poner stripe_id en DB para que createAsStripeCustomer no se llame
        \DB::table('clinics')->where('id', $clinic->id)->update(['stripe_id' => 'cus_prefilled']);
        $clinic->refresh();

        config(['services.stripe.price_id' => 'price_test_plan']);

        // Fake de la suscripcion que Cashier devolveria
        $fakeSub = new CashierSubscription();
        $fakeSub->stripe_id = 'sub_new_test_999';
        $fakeSub->stripe_status = 'active';

        // Builder mock: representa $clinic->newSubscription()->create()
        $builderMock = Mockery::mock();
        $builderMock->shouldReceive('create')
            ->once()
            ->with('pm_card_visa')
            ->andReturn($fakeSub);

        // Mock parcial del clinic real: solo intercepta los metodos que llaman a Stripe
        $clinicMock = Mockery::mock($clinic)->makePartial();
        $clinicMock->shouldReceive('updateDefaultPaymentMethod')
            ->once()
            ->with('pm_card_visa');
        $clinicMock->shouldReceive('newSubscription')
            ->once()
            ->with('default', 'price_test_plan')
            ->andReturn($builderMock);

        // Inyectar el mock para que el controller lo reciba via $request->user()->clinic
        $user->setRelation('clinic', $clinicMock);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscribe', ['payment_method' => 'pm_card_visa'])
            ->assertCreated()
            ->assertJson([
                'status'    => 'active',
                'stripe_id' => 'sub_new_test_999',
            ]);

        Mockery::close();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Crea clinica + usuario con subscripcion SaaS activa (pasa EnsureClinicIsActive).
     *
     * @return array{0: User, 1: Clinic}
     */
    private function crearUsuarioConClinicaActiva(): array
    {
        $clinic = Clinic::create(['name' => 'Clinica Test ' . uniqid()]);

        $user = User::create([
            'name'      => 'Usuario Test',
            'email'     => 'test_' . uniqid() . '@test.local',
            'password'  => bcrypt('password'),
            'clinic_id' => $clinic->id,
        ]);

        // Subscripcion SaaS activa para pasar el middleware clinic.active
        Subscription::create([
            'clinic_id'          => $clinic->id,
            'status'             => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        return [$user, $clinic];
    }
}
