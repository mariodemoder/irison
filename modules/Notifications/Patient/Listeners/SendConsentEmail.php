<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Listeners;

use App\Events\ConsentSent;
use App\Mail\ConsentSignRequestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendConsentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ConsentSent $event): void
    {
        try {
            $consent = $event->consent;
            $patientEmail = $consent->patient?->email;

            if ($patientEmail && filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::to($patientEmail)->queue(new ConsentSignRequestMail($consent));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send consent email', [
                'consent_id' => $event->consent->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
