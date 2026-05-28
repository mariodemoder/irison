@extends('backoffice.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-2xl font-semibold">Admin Users</h2>
        <a class="rounded bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-700" href="{{ route('backoffice.admin-users.create') }}">Nuevo admin</a>
    </div>

    <form class="mb-4 grid gap-3 rounded bg-white p-4 shadow-sm md:grid-cols-3" method="GET" action="{{ route('backoffice.admin-users.index') }}">
        <label class="text-sm">
            Rol
            <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="role">
                <option value="">Todos</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected($currentRole === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            Estado
            <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="status">
                <option value="">Todos</option>
                <option value="active" @selected($currentStatus === 'active')>Activo</option>
                <option value="inactive" @selected($currentStatus === 'inactive')>Inactivo</option>
            </select>
        </label>
        <div class="flex items-end">
            <button class="rounded bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-700" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="overflow-hidden rounded bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100 text-left">
                <tr>
                    <th class="px-3 py-2">Nombre</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Rol</th>
                    <th class="px-3 py-2">Activo</th>
                    <th class="px-3 py-2">Último acceso</th>
                    <th class="px-3 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adminUsers as $adminUser)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $adminUser->name }}</td>
                        <td class="px-3 py-2">{{ $adminUser->email }}</td>
                        <td class="px-3 py-2">{{ $adminUser->role }}</td>
                        <td class="px-3 py-2">{{ $adminUser->is_active ? 'Sí' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $adminUser->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex gap-2">
                                <a class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50" href="{{ route('backoffice.admin-users.edit', $adminUser) }}">Editar</a>
                                <form method="POST" action="{{ route('backoffice.admin-users.toggle', $adminUser) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50" type="submit">
                                        {{ $adminUser->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-4 text-slate-500" colspan="6">Sin admins internos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $adminUsers->links() }}
    </div>
@endsection
