{{-- Header de email para comunicaciones salientes de la clínica.
     Espera $clinic (App\Models\Clinic|null).
     - Clínica (con/sin logo)                     -> nombre de la clínica como TÍTULO CENTRADO en negrita (+ logo si lo tiene).
     - Sin clínica ($clinic null)                 -> logo Irison (fallback: comunicaciones staff/internas). --}}
<tr>
    <td align="center" style="padding:24px 24px 0 24px;">
        @if ($clinic)
            @if ($clinic->usesClinicBranding() && $clinic->hasClinicLogo())
                <a href="{{ config('app.url') }}" style="display:inline-block;margin-bottom:10px;">
                    <img src="{{ $clinic->clinicLogoUrl() }}" alt="{{ $clinic->name }}" style="height:50px;max-width:300px;width:auto;display:block;">
                </a>
            @endif
            <div style="display:block;padding:6px 0;font-size:22px;font-weight:700;color:#1f2937;line-height:1.2;text-align:center;">
                {{ $clinic->name }}
            </div>
        @else
            <a href="{{ config('app.url') }}" style="display:inline-block;">
                <img src="{{ asset('logo.svg') }}" alt="Irison" style="height:44px;display:block;">
            </a>
        @endif
    </td>
</tr>