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
  <h2>Nuevo mensaje de contacto</h2>

  <p class="label">Clínica</p>
  <p class="value">{{ $clinicName }} <span style="color:#9ca3af">(ID: {{ $clinicId }})</span></p>

  <p class="label">Remitente</p>
  <p class="value">{{ $senderName }} &lt;{{ $senderEmail }}&gt;</p>

  <p class="label">Asunto</p>
  <p class="value">{{ $subject }}</p>

  <p class="label">Mensaje</p>
  <div class="message-box">{{ $body }}</div>

  <div class="footer">Enviado desde Irison · irison.es</div>
</body>
</html>
