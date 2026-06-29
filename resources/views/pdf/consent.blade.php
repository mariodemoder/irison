<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consentimiento</title>
    <style>
        @page { margin: 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; line-height: 1.6; color: #1f2937; }
        .header { text-align: center; margin-bottom: 32px; border-bottom: 2px solid #4338ca; padding-bottom: 16px; }
        .header h1 { font-size: 18px; color: #4338ca; margin: 0 0 4px; }
        .header .clinic-name { font-size: 14px; color: #6b7280; }
        .content { margin-bottom: 32px; }
        .signature-area { margin-top: 48px; border-top: 1px solid #d1d5db; padding-top: 24px; }
        .signature-area .signature-svg { max-width: 300px; }
        .signature-area .label { font-weight: 600; color: #374151; }
        .metadata { margin-top: 32px; font-size: 10px; color: #9ca3af; }
        .metadata p { margin: 2px 0; }
        .footer { text-align: center; font-size: 10px; color: #9ca3af; margin-top: 48px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Consentimiento Informado</h1>
        <div class="clinic-name">{{ $clinicName }}</div>
    </div>

    <div class="content">
        {!! $contentHtml !!}
    </div>

    @if($signedAt)
    <div class="signature-area">
        <p class="label">Firmado por:</p>
        <p>{{ $patientName }}</p>
        @if($signatureSvg)
        <div class="signature-svg">{!! $signatureSvg !!}</div>
        @endif
        <p class="label">Fecha de firma:</p>
        <p>{{ $signedAt }}</p>
    </div>
    @endif

    <div class="metadata">
        <p>Documento generado por {{ $clinicName }} — {{ $generatedAt }}</p>
        @if($hash)
        <p>Integridad: {{ $hash }}</p>
        @endif
    </div>

    <div class="footer">
        {{ $clinicName }} — Software de gestión clínica
    </div>
</body>
</html>
