<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Factura {{ $document->counter ?? ('#' . $document->id) }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
    .header { margin-bottom: 16px; }
    .title { font-size: 20px; font-weight: 700; color: #1d4ed8; }
    .muted { color: #64748b; }
    .section { margin-top: 14px; }
    .section h3 { font-size: 13px; margin: 0 0 6px; color: #1e40af; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid #e2e8f0; padding: 7px 8px; vertical-align: top; }
    .label { width: 35%; font-weight: 700; background: #f8fafc; }
    .amount { font-size: 18px; font-weight: 700; color: #0f172a; }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">Factura {{ $document->counter ?? ('#' . $document->id) }}</div>
    <div class="muted">Fecha: {{ optional($document->date)->format('d/m/Y') ?? optional($document->created_at)->format('d/m/Y') }}</div>
  </div>

  <div class="section">
    <h3>Clínica</h3>
    <table>
      <tr><td class="label">Nombre</td><td>{{ $document->clinic_name ?? '—' }}</td></tr>
      <tr><td class="label">NIF</td><td>{{ $document->clinic_nif ?? '—' }}</td></tr>
      <tr><td class="label">Dirección</td><td>{{ $document->clinic_address ?? '—' }}</td></tr>
      <tr><td class="label">ZIP / Provincia / País</td><td>{{ trim(($document->clinic_zip ?? '') . ' ' . ($document->clinic_province ?? '') . ' ' . ($document->clinic_country ?? '')) ?: '—' }}</td></tr>
      <tr><td class="label">Usuario</td><td>{{ $document->user_full_name ?? '—' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>Paciente</h3>
    <table>
      <tr><td class="label">Nombre</td><td>{{ $document->patient_full_name ?? $document->patient?->name ?? '—' }}</td></tr>
      <tr><td class="label">NIF</td><td>{{ $document->patient_nif ?? $document->patient?->nif ?? '—' }}</td></tr>
      <tr><td class="label">Email</td><td>{{ $document->patient_email ?? $document->patient?->email ?? '—' }}</td></tr>
      <tr><td class="label">Teléfono</td><td>{{ $document->patient_phone ?? $document->patient?->phone ?? '—' }}</td></tr>
      <tr><td class="label">Dirección</td><td>{{ $document->patient_address ?? $document->patient?->address ?? '—' }}</td></tr>
      <tr><td class="label">ZIP</td><td>{{ $document->patient_zip ?? $document->patient?->zip ?? '—' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>Detalle</h3>
    <table>
      <tr><td class="label">Tipo</td><td>{{ $document->typeinvoice ?? '—' }}</td></tr>
      <tr><td class="label">Estado</td><td>{{ $document->status ?? '—' }}</td></tr>
      <tr><td class="label">Notas</td><td>{{ $document->notes ?? '—' }}</td></tr>
      <tr><td class="label">Importe</td><td class="amount">€ {{ number_format((float) $document->amount, 2, ',', '.') }}</td></tr>
    </table>
  </div>
</body>
</html>
