<?php

namespace App\Listeners;

use App\Events\ConsentSent;
use App\Mail\ConsentSignRequestMail;
use Illuminate\Support\Facades\Mail;

class SendConsentEmail
{
    public function handle(ConsentSent $event): void
    {
        $consent = $event->consent;
        $patient = $consent->patient;

        if (empty($patient->email)) {
            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $signUrl = $frontendUrl . '/sign/' . $consent->token;

        Mail::to($patient->email)
            ->send(new ConsentSignRequestMail($consent, $signUrl));
    }
}
