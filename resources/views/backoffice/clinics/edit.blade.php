@extends('backoffice.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-2xl font-semibold">Editar clínica</h2>
        <a class="text-sm text-slate-600 hover:text-slate-900" href="{{ route('backoffice.clinics.show', $clinic) }}">Volver</a>
    </div>

    <form class="space-y-4 rounded bg-white p-5 shadow-sm" method="POST" action="{{ route('backoffice.clinics.update', $clinic) }}">
        @csrf
        @method('PUT')

        <label class="block text-sm">
            Nombre
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="name" type="text" value="{{ old('name', $clinic->name) }}" required>
        </label>

        <label class="block text-sm">
            Slug
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="slug" type="text" value="{{ old('slug', $clinic->slug) }}" placeholder="clinica-demo">
        </label>

        <label class="block text-sm">
            Email
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="email" type="email" value="{{ old('email', $clinic->email) }}">
        </label>

        <label class="block text-sm">
            Teléfono
            <input class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="phone" type="text" value="{{ old('phone', $clinic->phone) }}">
        </label>

        <label class="block text-sm">
            Plan
            <select class="mt-1 w-full rounded border border-slate-300 px-3 py-2" name="plan" required>
                @foreach (['basic', 'pro', 'enterprise'] as $plan)
                    <option value="{{ $plan }}" data-max="{{ \App\Models\Clinic::PLAN_USER_LIMITS[$plan] ?? 3 }}"
                        @selected(old('plan', $clinic->plan ?: 'basic') === $plan)>{{ $plan }} — {{ \App\Models\Clinic::PLAN_USER_LIMITS[$plan] ?? 3 }} usuarios</option>
                @endforeach
            </select>
            <span class="mt-1 block text-xs text-slate-500">Cada plan incluye un límite de usuarios. Al cambiar de plan se actualiza automáticamente.</span>
        </label>

        <button class="rounded bg-slate-900 px-4 py-2 text-white hover:bg-slate-700" type="submit">Guardar cambios</button>
    </form>
@endsection
