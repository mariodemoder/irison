# Plan: Botón "Reenviar factura" en backoffice (asíncrono)

## Archivos a crear

### 1. `app/Jobs/ResendInvoiceEmail.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ResendInvoiceMail;
use App\Models\Clinic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Clinic $clinic,
        private readonly string $invoiceUrl,
    ) {}

    public function handle(): void
    {
        $recipient = $this->clinic->ownerUser()->first()
            ?? $this->clinic->users()->orderBy('id')->first();

        if (! $recipient || ! filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('resend_invoice.no_recipient', [
                'clinic_id' => $this->clinic->id,
            ]);

            return;
        }

        Mail::to($recipient->email)->queue(
            new ResendInvoiceMail($this->clinic->name, $this->invoiceUrl)
        );

        Log::info('resend_invoice.queued', [
            'clinic_id' => $this->clinic->id,
            'recipient' => $recipient->email,
        ]);
    }
}
```

### 2. `app/Mail/ResendInvoiceMail.php`

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResendInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $clinicName,
        public readonly string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura de Irison',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resend-invoice',
            with: [
                'clinicName' => $this->clinicName,
                'invoiceUrl' => $this->invoiceUrl,
            ],
        );
    }
}
```

### 3. `resources/views/emails/resend-invoice.blade.php`

```blade
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura de Irison</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px;background:#f6f8fc;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.08);">
                    @include('emails.partials.email-header')
                    <tr>
                        <td style="padding:24px 26px;">
                            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Tu factura</h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, aquí tienes el enlace a tu factura:</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
                                <tr>
                                    <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;">
                                        <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Ver factura</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

## Archivos a modificar

### 4. `routes/web.php` — línea 143-146, agregar ruta en grupo `super_admin,billing`

```php
Route::middleware('admin.role:super_admin,billing')->group(function () {
    Route::post('/clinics/{clinic}/cancel-subscription', [ClinicController::class, 'cancelSubscription'])->name('clinics.cancel-subscription');
    Route::patch('/clinics/{clinic}/change-plan', [ClinicController::class, 'changePlan'])->name('clinics.change-plan');
    Route::post('/clinics/{clinic}/resend-invoice', [ClinicController::class, 'resendInvoice'])->name('clinics.resend-invoice');  // NUEVO
});
```

### 5. `app/Http/Controllers/Backoffice/ClinicController.php` — agregar método

```php
public function resendInvoice(Request $request, Clinic $clinic): RedirectResponse
{
    $data = $request->validate([
        'invoice_url' => ['required', 'url'],
    ]);

    $owner = $clinic->ownerUser()->first()
        ?? $clinic->users()->orderBy('id')->first();

    if (! $owner || ! filter_var((string) $owner->email, FILTER_VALIDATE_EMAIL)) {
        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'No se encontró un destinatario válido para esta clínica.');
    }

    ResendInvoiceEmail::dispatch($clinic, $data['invoice_url']);

    return redirect()->route('backoffice.clinics.show', $clinic)
        ->with('status', 'Factura reenviada a ' . $owner->email . '. Se procesará en segundo plano.');
}
```

Agregar import: `use App\Jobs\ResendInvoiceEmail;`

### 6. `resources/views/backoffice/clinics/show.blade.php` — línea 328-335, agregar botón

Reemplazar la celda de Acción actual:

```blade
<td class="px-3 py-2">
    @if (! empty($invoice['hosted_invoice_url']))
        <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noreferrer">Ver</a>
        <form method="POST" action="{{ route('backoffice.clinics.resend-invoice', $clinic) }}" class="inline">
            @csrf
            <input type="hidden" name="invoice_url" value="{{ $invoice['hosted_invoice_url'] }}">
            <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">Reenviar</button>
        </form>
    @elseif (! empty($invoice['invoice_pdf']))
        <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ $invoice['invoice_pdf'] }}" target="_blank" rel="noreferrer">PDF</a>
    @else
        <span class="text-slate-400">-</span>
    @endif
</td>
```

## Flujo

```
1. Admin clic "Reenviar" → POST /backoffice/clinics/5/resend-invoice
2. Controller valida → dispatch ResendInvoiceEmail (queue)
3. Redirect back "Factura reenviada a owner@email.com"
4. Queue worker procesa job → ResendInvoiceMail → email con botón "Ver factura"
```
