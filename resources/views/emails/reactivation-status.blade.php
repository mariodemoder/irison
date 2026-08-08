<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de reactivación {{ $status }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    @include('emails.partials.email-header')
                    <tr>
                        <td style="padding:12px 24px 8px 24px;">
                            <h2 style="margin:0 0 12px 0;font-size:20px;color:#111827;">Solicitud de reactivación {{ $status }}</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px 24px;font-size:15px;line-height:1.6;">
                            <p style="margin:0 0 12px 0;">Hola,</p>
                            <p style="margin:0 0 12px 0;">Tu solicitud de reactivación de la cuenta de <strong>{{ $clinicName }}</strong> ha sido <strong>{{ $status }}</strong>.</p>
                            @if ($comments !== '-')
                                <p style="margin:0 0 12px 0;"><strong>Comentarios:</strong> {{ $comments }}</p>
                            @endif
                            @if ($status === 'aprobada')
                                <p style="margin:0 0 12px 0;">El equipo de Irison se pondrá en contacto contigo para completar el proceso de reactivación.</p>
                            @endif
                            <p style="margin:16px 0 0 0;color:#6b7280;">Equipo Irison</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
