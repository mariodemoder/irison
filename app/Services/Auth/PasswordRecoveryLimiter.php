<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;

class PasswordRecoveryLimiter
{
    public const MAX_EMAILS = 4;

    public function normalizeEmail(string $email): string
    {
        return trim(strtolower($email));
    }

    public function attempts(string $email): int
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '') {
            return 0;
        }

        return (int) Cache::get($this->key($normalized), 0);
    }

    public function canSend(string $email): bool
    {
        return $this->attempts($email) < self::MAX_EMAILS;
    }

    public function markSent(string $email): int
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '') {
            return 0;
        }

        $next = $this->attempts($normalized) + 1;
        Cache::forever($this->key($normalized), $next);

        return $next;
    }

    public function reset(string $email): void
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '') {
            return;
        }

        Cache::forget($this->key($normalized));
    }

    private function key(string $normalizedEmail): string
    {
        return 'auth:password-recovery:emails:' . sha1($normalizedEmail);
    }
}
