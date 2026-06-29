<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de upgrade {{ $status }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin: 0 0 12px;">Solicitud de upgrade {{ $status }}</h2>

    <p>Hola,</p>
    <p>La solicitud de cambio de plan de <strong>{{ $currentPlan }}</strong> a <strong>{{ $requestedPlan }}</strong> para <strong>{{ $clinicName }}</strong> ha sido <strong>{{ $status }}</strong>.</p>

    @if ($comments !== '-')
        <p><strong>Comentarios:</strong> {{ $comments }}</p>
    @endif

    <p style="margin-top: 14px; color: #6b7280;">Equipo Irison</p>
</body>
</html>
