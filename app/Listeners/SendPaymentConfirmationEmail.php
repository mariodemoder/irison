<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Mail\PaymentCompletedMail;
use App\Notifications\PaymentCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class SendPaymentConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentCompleted $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for payment notification');
            }

            $recipient->notify(new PaymentCompletedNotification($request));

            if (filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                $invoiceUrl = $this->resolveInvoiceUrl($request, $event->paymentData);

                Mail::to($recipient->email)->queue(new PaymentCompletedMail($request, $invoiceUrl));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveInvoiceUrl($request, array $paymentData): ?string
    {
        if (! empty($paymentData['invoice_url'])) {
            return $paymentData['invoice_url'];
        }

        if (! empty($paymentData['invoice_id'])) {
            return \App\Mail\SubscriptionActivatedMail::resolveInvoiceUrl($paymentData['invoice_id']);
        }

        if (! empty($request->stripe_checkout_session_id)) {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $session = $stripe->checkout->sessions->retrieve($request->stripe_checkout_session_id);
                if (! empty($session->invoice)) {
                    return \App\Mail\SubscriptionActivatedMail::resolveInvoiceUrl((string) $session->invoice);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not resolve invoice URL from checkout session', [
                    'session_id' => $request->stripe_checkout_session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $customerId = $request->clinic->stripe_id ?? $request->clinic->stripe_customer_id ?? null;
            if ($customerId) {
                $invoices = $stripe->invoices->all(['customer' => $customerId, 'limit' => 1]);
                if (count($invoices->data) > 0) {
                    return $invoices->data[0]->hosted_invoice_url ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not resolve invoice URL from customer', [
                'clinic_id' => $request->clinic_id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
