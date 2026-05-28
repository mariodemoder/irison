<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));

        $query = AdminUser::query()->orderByDesc('id');

        if ($role !== '' && in_array($role, AdminUser::ROLES, true)) {
            $query->where('role', $role);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('backoffice.admin_users.index', [
            'adminUsers' => $query->paginate(25)->withQueryString(),
            'roles' => AdminUser::ROLES,
            'currentRole' => $role,
            'currentStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('backoffice.admin_users.create', [
            'roles' => AdminUser::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:admin_users,email'],
            'role' => ['required', Rule::in(AdminUser::ROLES)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        AdminUser::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('backoffice.admin-users.index')
            ->with('status', 'Admin interno creado correctamente.');
    }

    public function edit(AdminUser $adminUser): View
    {
        return view('backoffice.admin_users.edit', [
            'adminUser' => $adminUser,
            'roles' => AdminUser::ROLES,
        ]);
    }

    public function update(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admin_users', 'email')->ignore($adminUser->id)],
            'role' => ['required', Rule::in(AdminUser::ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminUser->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        if (! empty($validated['password'])) {
            $adminUser->password = $validated['password'];
        }

        $adminUser->save();

        return redirect()->route('backoffice.admin-users.index')
            ->with('status', 'Admin interno actualizado correctamente.');
    }

    public function toggleActive(AdminUser $adminUser): RedirectResponse
    {
        $current = request()->user('admin');

        if ($current && $current->id === $adminUser->id && $adminUser->is_active) {
            return redirect()->route('backoffice.admin-users.index')
                ->withErrors(['status' => 'No puedes desactivarte a ti mismo.']);
        }

        $adminUser->is_active = ! $adminUser->is_active;
        $adminUser->save();

        return redirect()->route('backoffice.admin-users.index')
            ->with('status', 'Estado del admin actualizado.');
    }
}
