<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Suscripcion cancelada</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin: 0 0 12px;">Suscripcion cancelada en Irison</h2>

    <p><strong>Clinica:</strong> {{ $clinicName }}</p>
    <p><strong>Clinic ID:</strong> {{ $clinicId }}</p>
    <p><strong>Email clinica:</strong> {{ $clinicEmail ?: '-' }}</p>
    <p><strong>Stripe customer:</strong> {{ $stripeCustomerId ?: '-' }}</p>
    <p><strong>Stripe subscription:</strong> {{ $stripeSubscriptionId ?: '-' }}</p>

    <p style="margin-top: 14px; color: #6b7280;">Aviso automatico generado por cancelacion de suscripcion.</p>
</body>
</html>