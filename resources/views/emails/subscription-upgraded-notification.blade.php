<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu plan ha sido actualizado</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px;background:#f6f8fc;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.08);">
                    @include('emails.partials.email-header')
                    <tr>
                        <td style="padding:24px 26px;">
                            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">¡Tu plan ha sido actualizado!</h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, tu upgrade de suscripción ha sido completado correctamente.</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;color:#475569;">Plan anterior</td>
                                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $currentPlan }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Plan actual</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;color:#0f172a;text-transform:capitalize;" align="right">{{ $requestedPlan }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Fecha de confirmación</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;color:#0f172a;" align="right">{{ optional($completedAt)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                            @if(!empty($invoiceUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#059669,#0f766e);border-radius:10px;">
                                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Ver factura</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($reviewerComments) && $reviewerComments !== '-')
                        <tr>
                            <td style="padding:0 26px 20px 26px;">
                                <p style="margin:0;font-size:13px;color:#64748b;"><strong>Comentarios:</strong> {{ $reviewerComments }}</p>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
