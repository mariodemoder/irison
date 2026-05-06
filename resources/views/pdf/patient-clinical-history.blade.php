<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historia clínica {{ $patient->name ?? ('#' . $patient->id) }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
    .header { margin-bottom: 16px; }
    .title { font-size: 20px; font-weight: 700; color: #1d4ed8; }
    .muted { color: #64748b; }
    .section { margin-top: 14px; }
    .section h3 { font-size: 13px; margin: 0 0 6px; color: #1e40af; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #e2e8f0; padding: 7px 8px; vertical-align: top; }
    th { background: #f8fafc; text-align: left; }
    .meta td:first-child { width: 35%; font-weight: 700; background: #f8fafc; }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">Historia Clínica</div>
    <div class="muted">Fecha de emisión: {{ $issuedAt->format('d/m/Y H:i') }}</div>
  </div>

  <div class="section">
    <h3>Datos del paciente</h3>
    <table class="meta">
      <tr><td>Nombre</td><td>{{ $patient->name ?: '—' }}</td></tr>
      <tr><td>NIF</td><td>{{ $patient->nif ?: '—' }}</td></tr>
      <tr><td>Email</td><td>{{ $patient->email ?: '—' }}</td></tr>
      <tr><td>Teléfono</td><td>{{ $patient->phone ?: '—' }}</td></tr>
      <tr><td>Dirección</td><td>{{ $patient->address ?: '—' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>Datos de la clínica</h3>
    <table class="meta">
      <tr><td>Nombre</td><td>{{ $patient->clinic?->name ?: '—' }}</td></tr>
      <tr><td>NIF</td><td>{{ $patient->clinic?->nif ?: '—' }}</td></tr>
      <tr><td>Dirección</td><td>{{ $patient->clinic?->address ?: '—' }}</td></tr>
      <tr><td>ZIP / Provincia / País</td><td>{{ trim(($patient->clinic?->zip ?? '') . ' ' . ($patient->clinic?->province ?? '') . ' ' . ($patient->clinic?->country ?? '')) ?: '—' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>Datos del profesional</h3>
    <table class="meta">
      <tr><td>Nombre</td><td>{{ $professional?->name ?: '—' }}</td></tr>
      <tr><td>Email</td><td>{{ $professional?->email ?: '—' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h3>Historia</h3>
    <table>
      <thead>
        <tr>
          <th style="width: 28%">Fecha</th>
          <th>Observaciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($patient->appointments ?? []) as $appointment)
          <tr>
            <td>{{ optional($appointment->start_time)->format('d/m/Y') ?: '—' }}</td>
            <td>{{ trim((string) ($appointment->notes ?? '')) !== '' ? $appointment->notes : ($appointment->clinicalRecord?->notes ?: '—') }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="2">Sin citas registradas.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</body>
</html>
