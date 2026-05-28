<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 0 24px;">
                            <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Irison Trial · {{ strtoupper($milestone) }}</p>
                            <h1 style="margin:8px 0 0 0;font-size:22px;line-height:1.3;color:#020617;">{{ $headline }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 0 24px;font-size:15px;line-height:1.6;">
                            <p style="margin:0 0 10px 0;">Hola, equipo de {{ $clinicName }}.</p>
                            <p style="margin:0 0 12px 0;">{{ $messageBody }}</p>
                            @if($trialEndsAt)
                                <p style="margin:0 0 12px 0;color:#334155;"><strong>Fin estimado del trial:</strong> {{ \Illuminate\Support\Carbon::parse($trialEndsAt)->format('d/m/Y H:i') }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 24px 24px;">
                            <a href="{{ $billingUrl }}" style="display:inline-block;background:#0ea5e9;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px;">Ver opciones de conversión</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
