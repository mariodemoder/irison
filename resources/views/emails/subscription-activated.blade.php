@extends('emails.layouts.irison')

@section('title', 'Bienvenido a Irison')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">¡Bienvenido a Irison!</h1>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, tu plan <strong style="text-transform:capitalize;">{{ $plan }}</strong> ya está activo. Ya puedes disfrutar de todas las funcionalidades de Irison.</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;color:#475569;">Plan activo</td>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $plan }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Activado el</td>
                    <td style="padding:12px 14px;font-size:13px;font-weight:700;color:#0f172a;" align="right">{{ $activatedAt }}</td>
                </tr>
            </table>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Si tienes alguna duda, no dudes en contactarnos.</p>
            @if(!empty($invoiceUrl) && !empty($receiptUrl))
                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;padding-right:6px;">
                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar factura</a>
                        </td>
                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;padding-left:6px;">
                            <a href="{{ $receiptUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar recibo</a>
                        </td>
                    </tr>
                </table>
            @elseif(!empty($invoiceUrl))
                @include('emails.partials.email-cta', ['url' => $invoiceUrl, 'label' => 'Descargar factura'])
            @elseif(!empty($receiptUrl))
                @include('emails.partials.email-cta', ['url' => $receiptUrl, 'label' => 'Descargar recibo'])
            @endif
        </td>
    </tr>
@endsection
