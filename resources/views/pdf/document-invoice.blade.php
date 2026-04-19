<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{{ ($document->type ?? '') === 'abono' ? 'Factura rectificativa' : 'Factura' }} {{ $document->counter ?? ('#' . $document->id) }}</title>
  <style>
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
    .page { position: relative; min-height: 1040px; padding: 18px 50px; background: #fff; overflow: hidden; }
    .bg-layer { position: absolute; inset: 0; background-position: center; background-size: cover; background-repeat: no-repeat; opacity: 0.5; z-index: 1; }
    .content-layer { position: relative; z-index: 2; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 30px; margin-bottom: 20px; gap: 16px; }
    .header-left { flex: 0 0 auto; }
    .header-right { flex: 0 0 50%; text-align: right; }
    .header-right .company-name { font-size: 13px; font-weight: 700; color: #1e40af; margin-bottom: 4px; }
    .header-right .company-info { font-size: 11px; color: #121213; line-height: 1.5; }
    .title { font-size: 15px; font-weight: 700; color: #000000; }
    .date-label { font-size: 15px; color: #050505; margin-bottom: 8px; }
    .muted { color: #64748b; }
    .section { margin-top: 14px; }
    .section h3 { font-size: 13px; margin: 0 0 6px; color: #1e40af; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid #e2e8f0; padding: 7px 8px; vertical-align: top; }
    .label { font-weight: 700; }
    .label-w { width: 22%; }
    .label-sm { width: 14%; }
    .amount { font-size: 18px; font-weight: 700; color: #0f172a; }
  </style>
</head>
<body>
  <div class="page" style="margin-top: 10px">
    @if (!empty($invoiceBackgroundDataUri))
      <div class="bg-layer" style="background-image: url('{{ $invoiceBackgroundDataUri }}');"></div>
    @endif
    <div class="content-layer">
      <div class="header">
        <div class="header-left">
          <div class="title">{{ ($document->type ?? '') === 'abono' ? 'Factura Rectificativa Nº:' : 'Factura Nº:' }} {{ $document->counter ?? ('#' . $document->id) }}</div>
          <br>
          <div class="date-label">Fecha: {{ optional($document->date)->format('d/m/Y') ?? optional($document->created_at)->format('d/m/Y') }}</div>
          @if (($document->type ?? '') === 'abono' && !empty($originDocument?->counter))
            <div class="date-label">Factura Origen Nº: {{ $originDocument->counter }}</div>
          @endif
        </div>
        <div class="header-right">
          <div class="company-name">{{ $document->clinic_name ?? '—' }}</div>
          <div class="company-info">
            NIF: {{ $document->clinic_nif ?? '—' }}<br>
            {{ $document->clinic_address ?? '—' }}<br>
            {{ trim(($document->clinic_zip ?? '') . ' ' . ($document->clinic_province ?? '') . ' ' . ($document->clinic_country ?? '')) ?: '—' }}<br>
            Profesional: {{ $document->user_full_name ?? '—' }}
          </div>
        </div>
      </div>


      <div class="section" style="margin-top: 60px;">
          
        <h3>Paciente</h3>
        <table>
          <tr>
            <td class="label label-sm">Nombre</td><td>{{ $document->patient_full_name ?? $document->patient?->name ?? '—' }}</td>
            <td class="label label-sm">Dirección</td><td>{{ $document->patient_address ?? $document->patient?->address ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label label-sm">NIF</td><td>{{ $document->patient_nif ?? $document->patient?->nif ?? '—' }}</td>
            <td class="label label-sm">ZIP</td><td>{{ $document->patient_zip ?? $document->patient?->zip ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label label-sm">Email</td><td>{{ $document->patient_email ?? $document->patient?->email ?? '—' }}</td>
            <td class="label label-sm">Teléfono</td><td>{{ $document->patient_phone ?? $document->patient?->phone ?? '—' }}</td>
          </tr>
        </table>
      </div>

      @php
        $typeLabel = match($document->typeinvoice ?? '') {
          'appointment' => 'Sesión',
          'package', 'bonus', 'bono', 'pack' => 'Bono con sesiones',
          default => $document->typeinvoice ?? '—',
        };
        $statusLabel = match($document->status ?? '') {
          'issued'    => 'Emitida',
          'paid'      => 'Pagada',
          'pending'   => 'Pendiente',
          'cancelled' => 'Cancelada',
          'draft'     => 'Borrador',
          default     => $document->status ?? '—',
        };
        $isAppointment = ($document->typeinvoice ?? '') === 'appointment';
        $isBonus = in_array($document->typeinvoice ?? '', ['package', 'bonus', 'bono', 'pack']);
        $appointmentDate = optional($document->date)->format('d/m/Y') ?? optional($document->created_at)->format('d/m/Y');
        $bonusObj = $bonus ?? null;
        $amountFormatted = '€ ' . number_format((float) $document->amount, 2, ',', '.');
      @endphp
      <div class="section" style="margin-top: 20px;">
        <h3>Detalle</h3>
        @if ($isAppointment)
        <table>
          <thead>
            <tr>
              <td class="label" style="width:18%">Tipo</td>
              <td class="label" style="width:10%; text-align:center">Cantidad</td>
              <td class="label">Detalle</td>
              <td class="label" style="width:18%; text-align:right">Importe</td>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ $typeLabel }}</td>
              <td style="text-align:center">1</td>
              <td>{{ $appointmentDate }} - {{ $document->notes ?? '—' }}</td>
              <td style="text-align:right; font-weight:700">{{ $amountFormatted }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right; font-weight:700; border-top:2px solid #cbd5e1;"><u>TOTAL</u></td>
              <td class="amount" style="text-align:right; border-top:2px solid #cbd5e1;">{{ $amountFormatted }}</td>
            </tr>
          </tfoot>
        </table>
        @elseif ($isBonus)
        <table>
          <thead>
            <tr>
              <td class="label" style="width:18%">Tipo</td>
              <td class="label" style="width:10%; text-align:center">Cantidad</td>
              <td class="label">Detalle</td>
              <td class="label" style="width:18%; text-align:right">Importe</td>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ $typeLabel }}</td>
              <td style="text-align:center">1</td>
              <td>
                @if (!empty($bonusObj->name ?? $document->notes ?? null))
                  <strong>{{ $bonusObj->name ?? $document->notes }}</strong>
                @endif
                @if (!empty($bonusObj->total_sessions ?? null))
                  &nbsp;&nbsp;·&nbsp;&nbsp;{{ $bonusObj->total_sessions }} sesiones
                @endif
                @if (!empty($bonusObj->expires_at ?? null))
                  &nbsp;&nbsp;·&nbsp;&nbsp;Expira: {{ optional($bonusObj->expires_at)->format('d/m/Y') }}
                @endif
              </td>
              <td style="text-align:right; font-weight:700">{{ $amountFormatted }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right; font-weight:700; border-top:2px solid #cbd5e1;"><u>Total</u></td>
              <td class="amount" style="text-align:right; border-top:2px solid #cbd5e1;">{{ $amountFormatted }}</td>
            </tr>
          </tfoot>
        </table>
        @else
        @php
          $docItems = $document->items ?? collect();
          $hasItems = $docItems->isNotEmpty();
          // Calcular totales
          $baseTotal = 0;
          $taxBreakdown = []; // ['rate' => X, 'base' => Y, 'tax' => Z]
          foreach ($docItems as $item) {
            $lineBase = (float)$item->quantity * (float)$item->unit_price;
            $rate = (float)$item->tax_rate;
            $lineTax = $lineBase * ($rate / 100);
            $baseTotal += $lineBase;
            if (!isset($taxBreakdown[$rate])) {
              $taxBreakdown[$rate] = ['rate' => $rate, 'base' => 0, 'tax' => 0];
            }
            $taxBreakdown[$rate]['base'] += $lineBase;
            $taxBreakdown[$rate]['tax'] += $lineTax;
          }
          $taxTotal = array_sum(array_column($taxBreakdown, 'tax'));
          $grandTotal = $baseTotal + $taxTotal;
          ksort($taxBreakdown);
          $hasTax = $taxTotal > 0.001;
        @endphp
        @if ($hasItems)
        <table>
          <thead>
            <tr>
              <td class="label" style="width:40%">Descripción</td>
              <td class="label" style="width:8%; text-align:center">Cant.</td>
              <td class="label" style="width:15%; text-align:right">Precio unit.</td>
              <td class="label" style="width:10%; text-align:center">IVA %</td>
              <td class="label" style="width:15%; text-align:right">Base</td>
              <td class="label" style="width:12%; text-align:right">Total</td>
            </tr>
          </thead>
          <tbody>
            @foreach ($docItems->sortBy('sort_order') as $item)
            @php
              $lineBase = (float)$item->quantity * (float)$item->unit_price;
              $lineTotal = $lineBase * (1 + (float)$item->tax_rate / 100);
            @endphp
            <tr>
              <td>{{ $item->description ?: '—' }}</td>
              <td style="text-align:center">{{ rtrim(rtrim(number_format((float)$item->quantity, 4, ',', '.'), '0'), ',') }}</td>
              <td style="text-align:right">{{ '€ ' . number_format((float)$item->unit_price, 2, ',', '.') }}</td>
              <td style="text-align:center">{{ (float)$item->tax_rate > 0 ? number_format((float)$item->tax_rate, 0) . '%' : '—' }}</td>
              <td style="text-align:right">{{ '€ ' . number_format($lineBase, 2, ',', '.') }}</td>
              <td style="text-align:right; font-weight:600">{{ '€ ' . number_format($lineTotal, 2, ',', '.') }}</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="border:none; padding:4px 0;"></td>
              <td style="text-align:right; border-top:2px solid #cbd5e1; padding-top:6px; color:#64748b; font-size:11px">Base imponible</td>
              <td style="text-align:right; border-top:2px solid #cbd5e1; padding-top:6px; color:#64748b; font-size:11px">{{ '€ ' . number_format($baseTotal, 2, ',', '.') }}</td>
            </tr>
            @foreach ($taxBreakdown as $row)
            <tr>
              <td colspan="4" style="border:none; padding:2px 0;"></td>
              <td style="text-align:right; border:none; color:#64748b; font-size:11px">IVA {{ number_format($row['rate'], 0) }}%</td>
              <td style="text-align:right; border:none; color:#64748b; font-size:11px">{{ '€ ' . number_format($row['tax'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr>
              <td colspan="4" style="border:none; padding:2px 0;"></td>
              <td style="text-align:right; border-top:2px solid #0f172a; font-weight:700; padding-top:6px"><u>TOTAL</u></td>
              <td class="amount" style="text-align:right; border-top:2px solid #0f172a; padding-top:6px">{{ '€ ' . number_format($grandTotal, 2, ',', '.') }}</td>
            </tr>
          </tfoot>
        </table>
        @else
        <table>
          <tr><td class="label label-w">Tipo</td><td>{{ $typeLabel }}</td></tr>
          <tr><td class="label label-w">Detalle</td><td>{{ $document->notes ?? '—' }}</td></tr>
        </table>
        <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
          <table style="width: auto; min-width: 260px;">
            <tr>
              <td style="font-weight: 700; padding: 8px 12px; border: 0px solid #e2e8f0; text-align: right; white-space: nowrap;"><u>Total:</u></td>
              <td class="amount" style="padding: 8px 12px; border: 0px solid #e2e8f0; text-align: right; white-space: nowrap;">{{ $amountFormatted }}</td>
            </tr>
          </table>
        </div>
        @endif
        @endif
      </div>
    </div>
  </div>
</body>
</html>
