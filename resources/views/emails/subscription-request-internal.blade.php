<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de upgrade</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin: 0 0 12px;">Nueva solicitud de upgrade</h2>

    <p><strong>Cl&iacute;nica:</strong> {{ $clinicName }}</p>
    <p><strong>Clinic ID:</strong> {{ $clinicId }}</p>
    <p><strong>Email cl&iacute;nica:</strong> {{ $clinicEmail }}</p>
    <p><strong>Plan actual:</strong> {{ $currentPlan }}</p>
    <p><strong>Plan solicitado:</strong> {{ $requestedPlan }}</p>
    <p><strong>Comentarios:</strong> {{ $comments }}</p>
    <p><strong>Solicitado el:</strong> {{ $requestedAt }}</p>

    <p style="margin-top: 14px; color: #6b7280;">Revisa la solicitud desde el panel de administraci&oacute;n.</p>
</body>
</html>
