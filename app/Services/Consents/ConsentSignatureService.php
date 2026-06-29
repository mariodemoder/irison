<?php

namespace App\Services\Consents;

use App\Models\PatientConsent;
use Illuminate\Support\Str;

class ConsentSignatureService
{
    public function generateToken(PatientConsent $consent, int $ttlHours = 72): string
    {
        $token = Str::uuid()->toString();

        $consent->forceFill([
            'token' => $token,
            'token_expires_at' => now()->addHours($ttlHours),
        ])->save();

        return $token;
    }

    public function isTokenValid(PatientConsent $consent): bool
    {
        if (empty($consent->token)) {
            return false;
        }

        if ($consent->token_expires_at && $consent->token_expires_at->isPast()) {
            return false;
        }

        if (! in_array($consent->status, ['pending', 'sent'], true)) {
            return false;
        }

        return true;
    }

    public function sign(PatientConsent $consent, string $signatureSvg, array $meta = []): void
    {
        $consent->forceFill([
            'signature_svg' => $signatureSvg,
            'signed_at' => now(),
            'status' => 'signed',
            'ip' => $meta['ip'] ?? $consent->ip,
            'user_agent' => $meta['user_agent'] ?? $consent->user_agent,
            'signed_by' => $meta['signed_by'] ?? $consent->signed_by,
        ])->save();
    }
}
