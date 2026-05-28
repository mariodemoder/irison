<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingConnection();
    }

    private function assertSafeTestingConnection(): void
    {
        $defaultConnection = (string) config('database.default');
        $sqliteDatabase = (string) config('database.connections.sqlite.database');

        if (! app()->environment('testing') || $defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException('Los tests de Backoffice solo se permiten en APP_ENV=testing con sqlite :memory:.');
        }
    }

    public function test_super_admin_can_access_admin_users_index(): void
    {
        $superAdmin = AdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'super@index.test',
            'password' => 'password123',
            'role' => AdminUser::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'admin')->get('/backoffice/admin-users');

        $response->assertOk();
    }

    public function test_readonly_cannot_access_admin_users_index(): void
    {
        $readonly = AdminUser::query()->create([
            'name' => 'Readonly User',
            'email' => 'readonly@index.test',
            'password' => 'password123',
            'role' => AdminUser::ROLE_READONLY,
            'is_active' => true,
        ]);

        $response = $this->actingAs($readonly, 'admin')->get('/backoffice/admin-users');

        $response->assertForbidden();
    }
}
