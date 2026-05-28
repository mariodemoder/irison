<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_BILLING = 'billing';
    public const ROLE_READONLY = 'readonly';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_SUPPORT,
        self::ROLE_BILLING,
        self::ROLE_READONLY,
    ];

    protected $table = 'admin_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
