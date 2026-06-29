<?php

namespace App\Events;

use App\Models\PatientConsent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsentRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly PatientConsent $consent) {}
}
