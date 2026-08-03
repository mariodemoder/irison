<?php

namespace App\Listeners;

use App\Events\ConsentCreated;
use App\Events\ConsentRevoked;
use App\Events\ConsentSent;
use App\Events\ConsentSigned;
use App\Models\ConsentLog;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Request;

class LogConsentActivity
{
    public function handleCreated(ConsentCreated $event): void
    {
        $this->log($event->consent, 'created');
    }

    public function handleSent(ConsentSent $event): void
    {
        $this->log($event->consent, 'sent');
    }

    public function handleSigned(ConsentSigned $event): void
    {
        $this->log($event->consent, 'signed');
    }

    public function handleRevoked(ConsentRevoked $event): void
    {
        $this->log($event->consent, 'revoked');
    }

    private function log($consent, string $event): void
    {
        ConsentLog::create([
            'consent_id' => $consent->id,
            'event' => $event,
            'user_id' => $consent->signed_by ?? auth()->id(),
            'ip' => $consent->ip ?? Request::ip(),
            'user_agent' => $consent->user_agent ?? Request::userAgent(),
        ]);

        ActivityLogger::log(
            tenantId: (int) ($consent->clinic_id ?? 0),
            userId: (int) ($consent->user_id ?? auth()->id()),
            event: 'consent.' . $event,
            description: 'Consentimiento ' . $event,
            metadata: ['entity' => 'consent', 'entity_id' => (int) $consent->id],
            ip: $consent->ip ?? Request::ip(),
        );
    }
}
