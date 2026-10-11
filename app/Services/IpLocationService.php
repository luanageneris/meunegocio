<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpLocationService
{
    public function resolve(?string $ipAddress): string
    {
        $ipAddress = trim((string) $ipAddress);

        if ($ipAddress === '') {
            return 'Localizacao indisponivel';
        }

        if (app()->environment('testing')) {
            return 'Localizacao indisponivel';
        }

        if ($this->isPrivateOrReservedIp($ipAddress)) {
            return 'IP local ou privado';
        }

        return Cache::remember("ip-location:{$ipAddress}", now()->addDay(), function () use ($ipAddress) {
            try {
                $response = Http::acceptJson()
                    ->timeout(2)
                    ->get("http://ip-api.com/json/{$ipAddress}", [
                        'fields' => 'status,country,regionName,city',
                        'lang' => 'pt-BR',
                    ]);

                if (!$response->successful()) {
                    return 'Localizacao indisponivel';
                }

                $payload = $response->json();

                if (($payload['status'] ?? null) !== 'success') {
                    return 'Localizacao indisponivel';
                }

                $parts = array_values(array_filter([
                    $payload['city'] ?? null,
                    $payload['regionName'] ?? null,
                    $payload['country'] ?? null,
                ]));

                return count($parts) > 0
                    ? implode(' - ', $parts)
                    : 'Localizacao indisponivel';
            } catch (\Throwable) {
                return 'Localizacao indisponivel';
            }
        });
    }

    private function isPrivateOrReservedIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
