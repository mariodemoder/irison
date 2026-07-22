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
        private readonly string $subject,
        private readonly string $message,
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
            new ResendInvoiceMail($this->clinic->name, $this->invoiceUrl, $this->subject, $this->message)
        );

        Log::info('resend_invoice.queued', [
            'clinic_id' => $this->clinic->id,
            'recipient' => $recipient->email,
        ]);
    }
}
