<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bienvenido a Irison</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px;background:#f6f8fc;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.08);">
                    @include('emails.partials.email-header')
                    <tr>
                        <td style="padding:24px 26px;">
                            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">¡Bienvenido a Irison!</h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, tu plan <strong style="text-transform:capitalize;">{{ $plan }}</strong> ya está activo. Ya puedes disfrutar de todas las funcionalidades de Irison.</p>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Si tienes alguna duda, no dudes en contactarnos.</p>
                            @if(!empty($invoiceUrl) && !empty($receiptUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;padding-right:6px;">
                                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar factura</a>
                                        </td>
                                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;padding-left:6px;">
                                            <a href="{{ $receiptUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar recibo</a>
                                        </td>
                                    </tr>
                                </table>
                            @elseif(!empty($invoiceUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;">
                                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Descargar factura</a>
                                        </td>
                                    </tr>
                                </table>
                            @elseif(!empty($receiptUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;">
                                            <a href="{{ $receiptUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Descargar recibo</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 26px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            <strong>Plan activo:</strong> {{ ucfirst($plan) }}<br>
                            <strong>Activado el:</strong> {{ $activatedAt }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
