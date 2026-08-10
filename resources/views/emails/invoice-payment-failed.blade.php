@extends('emails.layouts.irison')

@section('title', 'Pago de suscripción pendiente')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">No hemos podido procesar el pago de tu suscripción</h1>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Hola{{ !empty($clinicName) ? ' ' . $clinicName : '' }},</p>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">
                Stripe ha notificado un fallo en el cobro de la factura
                <strong>{{ $invoiceId }}</strong>
                por un importe de
                <strong>{{ $amountDue }} {{ $currency }}</strong>.
            </p>
            @if(!empty($nextPaymentAttempt))
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Stripe volverá a intentar el cobro el {{ $nextPaymentAttempt }}.</p>
            @else
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Stripe reintentará el cobro automáticamente según su calendario.</p>
            @endif
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">
                Puedes revisar y actualizar tu método de pago desde tu panel de facturación para evitar interrupciones.
            </p>
        </td>
    </tr>
@endsection
