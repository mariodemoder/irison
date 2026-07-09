<?php

namespace App\Listeners;

use App\Events\SubscriptionUpgraded;
use App\Mail\SubscriptionUpgradedNotificationMail;
use App\Notifications\SubscriptionUpgradedNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;

class UpgradeSubscription
{
    use InteractsWithQueue;

    public function handle(SubscriptionUpgraded $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for upgraded notification');
            }

            $recipient->notify(new SubscriptionUpgradedNotification($request));

            // Enviar email de upgrade completado
            if (filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                $invoiceUrl = $this->resolveInvoiceUrl($request);

                Mail::to($recipient->email)->queue(
                    new SubscriptionUpgradedNotificationMail($request, $invoiceUrl)
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription upgraded notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveInvoiceUrl($request): ?string
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            // Prioridad 1: usar el session_id del checkout si existe
            if (! empty($request->stripe_checkout_session_id)) {
                $session = $stripe->checkout->sessions->retrieve($request->stripe_checkout_session_id);
                if (! empty($session->invoice)) {
                    $invoice = $stripe->invoices->retrieve($session->invoice);

                    return $invoice->hosted_invoice_url ?? null;
                }
            }

            // Prioridad 2: última factura del customer
            $customerId = $request->clinic->stripe_id ?? $request->clinic->stripe_customer_id ?? null;
            if ($customerId) {
                $invoices = $stripe->invoices->all(['customer' => $customerId, 'limit' => 1]);
                if (count($invoices->data) > 0) {
                    return $invoices->data[0]->hosted_invoice_url ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not retrieve invoice URL for upgraded notification', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}