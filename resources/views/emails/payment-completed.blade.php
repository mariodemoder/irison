@extends('emails.layouts.irison')

@section('title', 'Comprobante de pago')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Pago confirmado</h1>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, este es tu comprobante del cambio de plan:</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;color:#475569;">Plan anterior</td>
                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;text-transform:capitalize;" align="right">{{ $currentPlan }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Plan actual</td>
                    <td style="padding:12px 14px;font-size:13px;font-weight:700;text-transform:capitalize;" align="right">{{ $requestedPlan }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Fecha de confirmación</td>
                    <td style="padding:12px 14px;font-size:13px;font-weight:700;" align="right">{{ optional($completedAt)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
                </tr>
            </table>
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
