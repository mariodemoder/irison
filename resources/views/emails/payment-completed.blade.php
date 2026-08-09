<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprobante de pago</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px;background:#f6f8fc;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#059669,#0f766e);padding:24px 26px;color:#fff;">
                            <a href="{{ config('app.url') }}" style="display:inline-block;">
                                <img src="{{ asset('logo.svg') }}" alt="Irison" style="height:36px;display:block;">
                            </a>
                            <h1 style="margin:16px 0 0;font-size:22px;line-height:1.25;">Pago confirmado</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.95;">Tu upgrade se ha procesado correctamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 26px;">
                            <p style="margin:0 0 14px;font-size:14px;color:#334155;">Hola {{ $clinicName }}, este es tu comprobante del cambio de plan:</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;color:#475569;">Plan anterior</td>
                                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;text-transform:capitalize;" align="right">{{ $currentPlan }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Plan actual</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;text-transform:capitalize;" align="right">{{ $requestedPlan }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;font-size:13px;color:#475569;">Fecha de confirmación</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;" align="right">{{ optional($completedAt)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                            @if(!empty($invoiceUrl) && !empty($receiptUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#059669,#0f766e);border-radius:10px;padding-right:6px;">
                                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar factura</a>
                                        </td>
                                        <td style="background:linear-gradient(135deg,#059669,#0f766e);border-radius:10px;padding-left:6px;">
                                            <a href="{{ $receiptUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap;">Descargar recibo</a>
                                        </td>
                                    </tr>
                                </table>
                            @elseif(!empty($invoiceUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#059669,#0f766e);border-radius:10px;">
                                            <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Descargar factura</a>
                                        </td>
                                    </tr>
                                </table>
                            @elseif(!empty($receiptUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#059669,#0f766e);border-radius:10px;">
                                            <a href="{{ $receiptUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Descargar recibo</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
