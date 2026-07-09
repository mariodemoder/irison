<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago de suscripcion pendiente</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    @include('emails.partials.email-header')
                    <tr>
                        <td style="padding:12px 24px 8px 24px;">
                            <h2 style="margin:0 0 12px 0;font-size:20px;color:#111827;">No hemos podido procesar el pago de tu suscripcion</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px 24px;font-size:15px;line-height:1.6;">
                            <p style="margin:0 0 12px 0;">Hola{{ !empty($clinicName) ? ' ' . $clinicName : '' }},</p>
                            <p style="margin:0 0 12px 0;">
                                Stripe ha notificado un fallo en el cobro de la factura
                                <strong>{{ $invoiceId }}</strong>
                                por un importe de
                                <strong>{{ $amountDue }} {{ $currency }}</strong>.
                            </p>
                            @if(!empty($nextPaymentAttempt))
                                <p style="margin:0 0 12px 0;">Stripe volvera a intentar el cobro el {{ $nextPaymentAttempt }}.</p>
                            @else
                                <p style="margin:0 0 12px 0;">Stripe reintentara el cobro automaticamente segun su calendario.</p>
                            @endif
                            <p style="margin:0 0 12px 0;">
                                Puedes revisar y actualizar tu metodo de pago desde tu panel de facturacion para evitar interrupciones.
                            </p>
                            <p style="margin:16px 0 0 0;color:#6b7280;">Equipo Irison</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
