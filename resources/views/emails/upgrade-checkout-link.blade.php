@extends('emails.layouts.irison')

@section('title', 'Completa tu upgrade')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Tu upgrade está listo</h1>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, completa el pago para activar tu nuevo plan.</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;color:#475569;">Plan actual</td>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $currentPlan }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Nuevo plan</td>
                    <td style="padding:12px 14px;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $requestedPlan }}</td>
                </tr>
            </table>
            @include('emails.partials.email-cta', ['url' => $checkoutUrl, 'label' => 'Completar pago del upgrade'])
            <p style="margin:20px 0 8px;font-size:12px;color:#64748b;">Si el botón no abre, copia este enlace:</p>
            <p style="margin:0;word-break:break-all;font-size:12px;color:#1e293b;">{{ $checkoutUrl }}</p>
            <p style="margin:20px 0 0;font-size:12px;color:#94a3b8;line-height:1.7;">
                <strong>Clínica:</strong> {{ $clinicName }} (ID {{ $clinicId }})<br>
                <strong>Email:</strong> {{ $clinicEmail }}<br>
                @if (!empty($sessionId))
                    <strong>Sesión:</strong> {{ $sessionId }}
                @endif
            </p>
        </td>
    </tr>
@endsection
