{{-- Header de email para comunicaciones salientes de la clínica.
     Espera $clinic (App\Models\Clinic|null).
     - PRO (pro/enterprise) con logo subido  -> logo de la clínica.
     - Plan basic / trial                    -> logo Irison grisado (marca de agua) + nombre de la clínica en negrita.
     - PRO sin logo / sin clínica            -> logo Irison (fallback por defecto). --}}
<tr>
    <td align="center" style="padding:24px 24px 0 24px;">
        @if ($clinic && $clinic->usesClinicBranding() && $clinic->hasClinicLogo())
            <a href="{{ config('app.url') }}" style="display:inline-block;">
                <img src="{{ $clinic->clinicLogoUrl() }}" alt="{{ $clinic->name }}" style="height:50px;max-width:300px;width:auto;display:block;">
            </a>
        @elseif ($clinic && ! $clinic->usesClinicBranding())
            <a href="{{ config('app.url') }}" style="display:inline-block;">
                <img src="{{ asset('logo.svg') }}" alt="Irison" style="height:44px;display:block;opacity:0.35;filter:grayscale(1);">
            </a>
            <div style="display:block;margin-top:8px;font-size:22px;font-weight:700;color:#6b7280;line-height:1.2;">
                {{ $clinic->name }}
            </div>
        @else
            <a href="{{ config('app.url') }}" style="display:inline-block;">
                <img src="{{ asset('logo.svg') }}" alt="Irison" style="height:44px;display:block;">
            </a>
        @endif
    </td>
</tr>
