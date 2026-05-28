<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use RuntimeException;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingConnection();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function assertSafeTestingConnection(): void
    {
        $defaultConnection = (string) config('database.default');
        $sqliteDatabase = (string) config('database.connections.sqlite.database');

        if (! app()->environment('testing') || $defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException('Los tests de Backoffice solo se permiten en APP_ENV=testing con sqlite :memory:.');
        }
    }

    public function test_backoffice_login_screen_is_available(): void
    {
        $response = $this->get('/backoffice/login');

        $response->assertOk();
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = AdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'super@irison.test',
            'password' => 'password123',
            'role' => AdminUser::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->post('/backoffice/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('backoffice.dashboard', absolute: false));
        $this->assertAuthenticated('admin');
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = AdminUser::query()->create([
            'name' => 'Inactive Admin',
            'email' => 'inactive@irison.test',
            'password' => 'password123',
            'role' => AdminUser::ROLE_SUPPORT,
            'is_active' => false,
        ]);

        $response = $this->from('/backoffice/login')->post('/backoffice/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/backoffice/login');
        $this->assertGuest('admin');
    }
}
