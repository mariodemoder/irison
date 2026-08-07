<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Infrastructure\Mail;

use App\Models\Clinic;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Clinic $clinic,
        public object $invoice,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pago de suscripcion pendiente en Irison',
        );
    }

    public function content(): Content
    {
        $amountDueCents = (int) ($this->invoice->amount_due ?? 0);
        $currency = strtoupper((string) ($this->invoice->currency ?? 'EUR'));
        $amountDue = number_format($amountDueCents / 100, 2, ',', '.');

        $nextPaymentAttempt = null;
        $nextPaymentAttemptRaw = $this->invoice->next_payment_attempt ?? null;
        if (is_numeric($nextPaymentAttemptRaw)) {
            $nextPaymentAttempt = Carbon::createFromTimestamp((int) $nextPaymentAttemptRaw)
                ->timezone($this->clinic->timezone ?: config('app.timezone'))
                ->format('d/m/Y H:i');
        }

        return new Content(
            view: 'emails.invoice-payment-failed',
            with: [
                'clinicName' => $this->clinic->name,
                'invoiceId' => (string) ($this->invoice->id ?? '-'),
                'amountDue' => $amountDue,
                'currency' => $currency,
                'nextPaymentAttempt' => $nextPaymentAttempt,
            ],
        );
    }
}
