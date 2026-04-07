<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activar cuenta</title>
</head>
<body style="margin:0;padding:0;background:#f7fafc;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7fafc;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 8px 24px;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;color:#111827;">Activa tu cuenta</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 0 24px;font-size:15px;line-height:1.6;">
                            <p style="margin:0 0 12px 0;">Hola {{ $name }},</p>
                            <p style="margin:0 0 12px 0;">Tu cuenta en DueleAhi ya fue creada. Para iniciar tu periodo de prueba, confirma tu email haciendo clic en este botón:</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 8px 24px;">
                            <a href="{{ $activationUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px;">Activar cuenta e iniciar trial</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 24px 24px;font-size:13px;line-height:1.5;color:#6b7280;">
                            <p style="margin:0 0 8px 0;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                            <p style="margin:0;word-break:break-all;">{{ $activationUrl }}</p>
                            <p style="margin:12px 0 0 0;">Este enlace vence en 24 horas.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
