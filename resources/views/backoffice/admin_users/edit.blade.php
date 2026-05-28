@extends('backoffice.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-2xl font-semibold">Editar admin interno</h2>
        <a class="text-sm text-slate-600 hover:text-slate-900" href="{{ route('backoffice.admin-users.index') }}">Volver</a>
    </div>

    <form class="space-y-4 rounded bg-white p-5 shadow-sm" method="POST" action="{{ route('backoffice.admin-users.update', $adminUser) }}">
        @csrf
        @method('PUT')

        <label class="block text-sm">
            Nombre
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="name" type="text" value="{{ old('name', $adminUser->name) }}" required>
        </label>

        <label class="block text-sm">
            Email
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="email" type="email" value="{{ old('email', $adminUser->email) }}" required>
        </label>

        <label class="block text-sm">
            Rol
            <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role', $adminUser->role) === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            Nueva contraseña (opcional)
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="password" type="password">
        </label>

        <label class="block text-sm">
            Confirmar nueva contraseña
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="password_confirmation" type="password">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $adminUser->is_active))> Activo
        </label>

        <button class="rounded bg-slate-900 px-4 py-2 text-white hover:bg-slate-700" type="submit">Guardar cambios</button>
    </form>
@endsection
