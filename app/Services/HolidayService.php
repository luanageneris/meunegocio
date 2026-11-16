<?php

namespace App\Services;

use App\Models\HolidaySync;
use App\Models\UserHolidayLocation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HolidayService
{
    private const OPEN_URL = 'https://openholidaysapi.org';

    public function countries(): array
    {
        return Cache::remember('holiday-countries-v1', now()->addDay(), function () {
            return $this->openData('/Countries', ['languageIsoCode' => 'PT']);
        });
    }

    public function subdivisions(string $country): array
    {
        if ($country === 'BR') {
            return Cache::remember('holiday-brazil-states-v1', now()->addDay(), function () {
                $states = $this->jsonList('https://brasilapi.com.br/api/ibge/uf/v1');

                return array_map(fn (array $state) => [
                    'code' => 'BR-'.$state['sigla'],
                    'name' => [['language' => 'PT', 'text' => $state['nome']]],
                    'ibge_id' => $state['id'],
                ], $states);
            });
        }

        return Cache::remember('holiday-subdivisions-v1-'.$country, now()->addDay(), function () use ($country) {
            return $this->openData('/Subdivisions', ['countryIsoCode' => $country, 'languageIsoCode' => 'PT']);
        });
    }

    public function cities(string $country, array $region): array
    {
        if ($country !== 'BR') {
            return $region['children'] ?? [];
        }

        $municipalities = Cache::remember('holiday-brazil-municipalities-v1', now()->addDay(), function () {
            $data = $this->jsonList('https://raw.githubusercontent.com/kelvins/Municipios-Brasileiros/main/json/municipios.json');

            return array_map(fn (array $city) => [
                'code' => 'IBGE-'.$city['codigo_ibge'],
                'name' => [['language' => 'PT', 'text' => $city['nome']]],
                'state_id' => $city['codigo_uf'],
            ], $data);
        });

        return array_values(array_filter($municipalities, fn (array $city) => $city['state_id'] === $region['ibge_id']));
    }

    public function synchronize(UserHolidayLocation $location, int $year): HolidaySync
    {
        $sync = HolidaySync::firstOrCreate(['location_key' => $location->cacheKey(), 'year' => $year]);

        return DB::transaction(function () use ($sync, $location, $year) {
            $sync = HolidaySync::query()->lockForUpdate()->findOrFail($sync->id);
            if ($sync->status === 'complete' && $sync->synced_at?->gt(now()->subDays(365))) {
                return $sync;
            }
            if ($sync->attempted_at?->gt(now()->subHour())) {
                return $sync;
            }

            try {
                $records = $this->openData('/PublicHolidays', [
                    'countryIsoCode' => $location->country_code,
                    'validFrom' => "$year-01-01",
                    'validTo' => "$year-12-31",
                ]);
                if ($records === []) {
                    throw new RuntimeException('A fonte não informou dados para este ano.');
                }
                $holidays = $this->normalizeOpenHolidays($records, $location);
                $source = 'OpenHolidays';
                $status = 'complete';
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::warning('Falha ao consultar feriados na OpenHolidays.', ['error' => $exception->getMessage()]);
                if ($location->country_code !== 'BR') {
                    return $this->markFailure($sync);
                }
                try {
                    $response = Http::acceptJson()->timeout(10)->get("https://brasilapi.com.br/api/feriados/v1/$year")->throw();
                    $records = $response->json();
                    if (! is_array($records) || ! array_is_list($records) || $records === []) {
                        throw new RuntimeException('A fonte alternativa não informou dados para este ano.');
                    }
                    $holidays = $this->normalizeBrazilHolidays($records);
                    $source = 'BrasilAPI';
                    $status = 'partial';
                } catch (ConnectionException|RequestException|RuntimeException $fallbackException) {
                    Log::warning('Falha ao consultar feriados na BrasilAPI.', ['error' => $fallbackException->getMessage()]);

                    return $this->markFailure($sync);
                }
            }

            // Uma resposta alternativa não deve substituir dados completos já obtidos.
            if ($status === 'partial' && $sync->source === 'OpenHolidays' && $sync->synced_at) {
                $sync->update(['status' => 'stale', 'attempted_at' => now()]);

                return $sync;
            }

            $sync->holidays()->delete();
            $sync->holidays()->createMany($holidays);
            $sync->update(['source' => $source, 'status' => $status, 'synced_at' => now(), 'attempted_at' => now()]);

            return $sync;
        });
    }

    private function markFailure(HolidaySync $sync): HolidaySync
    {
        $sync->update(['status' => $sync->synced_at ? 'stale' : 'failed', 'attempted_at' => now()]);

        return $sync;
    }

    private function openData(string $path, array $parameters): array
    {
        $data = Http::acceptJson()->timeout(10)->get(self::OPEN_URL.$path, $parameters)->throw()->json();
        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException('Resposta inválida da fonte de feriados.');
        }

        return $data;
    }

    private function jsonList(string $url): array
    {
        $body = Http::acceptJson()->timeout(15)->get($url)->throw()->body();
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Resposta inválida do catálogo de localidades.', previous: $exception);
        }
        if (! is_array($data) || ! array_is_list($data) || $data === []) {
            throw new RuntimeException('Resposta inválida do catálogo de localidades.');
        }

        return $data;
    }

    private function normalizeOpenHolidays(array $records, UserHolidayLocation $location): array
    {
        $holidays = [];
        foreach ($records as $record) {
            if (! is_array($record) || ! isset($record['id'], $record['startDate'], $record['endDate'], $record['name'], $record['nationwide'])
                || ! is_string($record['id']) || ! is_string($record['startDate']) || ! is_string($record['endDate'])
                || ! is_array($record['name']) || ! is_bool($record['nationwide'])
                || (isset($record['subdivisions']) && ! is_array($record['subdivisions']))) {
                throw new RuntimeException('A fonte retornou um feriado inválido.');
            }
            if (! in_array($record['type'] ?? null, ['Public', 'Optional'], true)) {
                continue;
            }
            $codes = array_column($record['subdivisions'] ?? [], 'code');
            $regionMatch = $location->region_code !== '' && in_array($location->region_code, $codes, true);
            $cityMatch = $location->city_code !== '' && in_array($location->city_code, $codes, true);
            if (! $record['nationwide'] && ! $regionMatch && ! $cityMatch) {
                continue;
            }
            $names = $record['name'];
            $name = collect($names)->firstWhere('language', 'PT')['text'] ?? ($names[0]['text'] ?? null);
            if (! is_string($name) || $name === '' || mb_strlen($name) > 255 || mb_strlen($record['id']) > 255
                || ! $this->validDate($record['startDate']) || ! $this->validDate($record['endDate']) || $record['startDate'] > $record['endDate']) {
                throw new RuntimeException('A fonte retornou uma data de feriado inválida.');
            }
            $holidays[$record['id']] = [
                'external_key' => (string) $record['id'],
                'start_date' => $record['startDate'],
                'end_date' => $record['endDate'],
                'name' => $name,
                'type' => $record['type'] === 'Public' ? 'public' : 'optional',
                'scope' => $record['nationwide'] ? 'national' : ($cityMatch ? 'local' : 'regional'),
            ];
        }

        return array_values($holidays);
    }

    private function normalizeBrazilHolidays(array $records): array
    {
        $holidays = [];
        foreach ($records as $record) {
            if (! is_array($record) || ! is_string($record['name'] ?? null) || $record['name'] === '' || mb_strlen($record['name']) > 255
                || ! is_string($record['date'] ?? null) || ! $this->validDate($record['date'])) {
                throw new RuntimeException('A fonte alternativa retornou uma data inválida.');
            }
            $key = hash('sha256', $record['date'].'|'.$record['name']);
            $holidays[$key] = [
                'external_key' => $key,
                'start_date' => $record['date'],
                'end_date' => $record['date'],
                'name' => $record['name'],
                'type' => 'unknown',
                'scope' => 'national',
            ];
        }

        return array_values($holidays);
    }

    private function validDate(string $date): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
