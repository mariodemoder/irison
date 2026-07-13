<?php

namespace App\Listeners;

use App\Events\ConsentSent;
use App\Mail\ConsentSignRequestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendConsentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            ->queue(new ConsentSignRequestMail($consent, $signUrl));
    }
}
