<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginThrottleService
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_MINUTES = 15;

    public function makeThrottleKey(string $email, ?string $ipAddress): string
    {
        return Str::transliterate(Str::lower(trim($email)).'|'.trim((string) $ipAddress));
    }

    /**
     * @param  array<int, string|null>  $ipAddresses
     */
    public function clearAttempts(string $email, array $ipAddresses = []): int
    {
        $keys = [];

        foreach ($ipAddresses as $ipAddress) {
            $keys[] = $this->makeThrottleKey($email, $ipAddress);
        }

        // Também remove eventual chave sem IP definido.
        $keys[] = $this->makeThrottleKey($email, null);

        $cleared = 0;

        foreach (array_unique($keys) as $key) {
            RateLimiter::clear($key);
            $cleared++;
        }

        return $cleared;
    }
}
