<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <style>
    body { font-family: sans-serif; font-size: 14px; color: #1f2937; }
    .label { font-weight: 600; color: #374151; margin-top: 12px; }
    .value { margin: 2px 0 8px; }
    .message-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; white-space: pre-wrap; }
    .footer { margin-top: 24px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }
  </style>
</head>
<body>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
          @include('emails.partials.email-header')
          <tr>
            <td style="padding:12px 24px 8px 24px;">
              <h2 style="margin:0 0 12px;font-size:20px;color:#111827;">Nuevo mensaje de contacto</h2>
            </td>
          </tr>
          <tr>
            <td style="padding:0 24px 24px 24px;font-size:14px;line-height:1.5;color:#1f2937;">
              <p style="margin:0 0 4px;"><strong style="color:#374151;">Cl&iacute;nica</strong></p>
              <p style="margin:0 0 12px;">{{ $clinicName }} <span style="color:#9ca3af">(ID: {{ $clinicId }})</span></p>
              <p style="margin:0 0 4px;"><strong style="color:#374151;">Remitente</strong></p>
              <p style="margin:0 0 12px;">{{ $senderName }} &lt;{{ $senderEmail }}&gt;</p>
              <p style="margin:0 0 4px;"><strong style="color:#374151;">Asunto</strong></p>
              <p style="margin:0 0 12px;">{{ $subject }}</p>
              <p style="margin:0 0 4px;"><strong style="color:#374151;">Mensaje</strong></p>
              <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;white-space:pre-wrap;">{{ $body }}</div>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">
              Enviado desde Irison &middot; irison.es
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
