<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago de suscripcion pendiente</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin-bottom: 12px;">No hemos podido procesar el pago de tu suscripcion</h2>

    <p>Hola{{ !empty($clinicName) ? ' ' . $clinicName : '' }},</p>

    <p>
        Stripe ha notificado un fallo en el cobro de la factura
        <strong>{{ $invoiceId }}</strong>
        por un importe de
        <strong>{{ $amountDue }} {{ $currency }}</strong>.
    </p>

    @if(!empty($nextPaymentAttempt))
        <p>Stripe volvera a intentar el cobro el {{ $nextPaymentAttempt }}.</p>
    @else
        <p>Stripe reintentara el cobro automaticamente segun su calendario.</p>
    @endif

    <p>
        Puedes revisar y actualizar tu metodo de pago desde tu panel de facturacion para evitar interrupciones.
    </p>

    <p>Equipo Irison</p>
</body>
</html>
