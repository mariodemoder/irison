<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Completa tu upgrade</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);padding:26px 28px;color:#ffffff;">
                            <a href="{{ config('app.url') }}" style="display:inline-block;">
                                <img src="{{ asset('logo.svg') }}" alt="Irison" style="height:36px;display:block;">
                            </a>
                            <h1 style="margin:16px 0 0;font-size:24px;line-height:1.25;">Tu upgrade est\u00e1 listo</h1>
                            <p style="margin:10px 0 0;font-size:14px;opacity:.95;">Hola {{ $clinicName }}, completa el pago para activar tu nuevo plan.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px;background:#f8fafc;font-size:13px;color:#475569;">Plan actual</td>
                                    <td style="padding:14px 16px;background:#f8fafc;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $currentPlan }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#475569;">Nuevo plan</td>
                                    <td style="padding:14px 16px;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $requestedPlan }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 28px 4px;">
                            <a href="{{ $checkoutUrl }}" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 20px;border-radius:10px;">Completar pago del upgrade</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 28px 22px;">
                            <p style="margin:0 0 8px;font-size:12px;color:#64748b;">Si el bot\u00f3n no abre, copia este enlace:</p>
                            <p style="margin:0;word-break:break-all;font-size:12px;color:#1e293b;">{{ $checkoutUrl }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            <strong>Cl\u00ednica:</strong> {{ $clinicName }} (ID {{ $clinicId }})<br>
                            <strong>Email:</strong> {{ $clinicEmail }}<br>
                            @if (!empty($sessionId))
                                <strong>Sesi\u00f3n:</strong> {{ $sessionId }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
