<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHolidayLocationRequest;
use App\Models\UserHolidayLocation;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HolidayController extends Controller
{
    public function __construct(private HolidayService $holidays) {}

    public function countries(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->holidays->countries()]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return $this->catalogError($exception);
        }
    }

    public function subdivisions(Request $request): JsonResponse
    {
        $validated = $request->validate(['country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/']]);
        try {
            return response()->json(['data' => $this->holidays->subdivisions($validated['country'])]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return $this->catalogError($exception);
        }
    }

    public function cities(Request $request): JsonResponse
    {
        $input = $request->validate([
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'region' => ['required', 'string', 'max:100'],
        ]);
        try {
            $region = collect($this->holidays->subdivisions($input['country']))->firstWhere('code', $input['region']);
            if (! $region) {
                throw ValidationException::withMessages(['region' => 'Selecione uma região disponível.']);
            }

            return response()->json(['data' => $this->holidays->cities($input['country'], $region)]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return $this->catalogError($exception);
        }
    }

    public function location(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->holidayLocation()->first()]);
    }

    public function updateLocation(UpdateHolidayLocationRequest $request): JsonResponse
    {
        try {
            [$attributes] = $this->resolveLocation($request->validated());
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return $this->catalogError($exception);
        }

        $location = $request->user()->holidayLocation()->updateOrCreate([], $attributes + ['consented_at' => now()]);

        return response()->json(['data' => $location, 'message' => 'Localização salva com sucesso.']);
    }

    public function preview(UpdateHolidayLocationRequest $request): JsonResponse
    {
        $period = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
        try {
            [$attributes, $cityVerified] = $this->resolveLocation($request->validated());
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return $this->catalogError($exception);
        }

        $sync = $this->holidays->synchronize(new UserHolidayLocation($attributes), (int) $period['year']);
        $first = CarbonImmutable::create((int) $period['year'], (int) $period['month'], 1)->startOfDay();
        $count = $sync->holidays()->where('start_date', '<=', $first->endOfMonth()->toDateString())
            ->where('end_date', '>=', $first->toDateString())->count();

        return response()->json(['data' => [
            'city_verified' => $cityVerified,
            'status' => $sync->status,
            'holiday_count' => $count,
            'source' => $sync->source,
            'year' => (int) $period['year'],
            'month' => (int) $period['month'],
        ]]);
    }

    private function resolveLocation(array $input): array
    {
        $country = collect($this->holidays->countries())->firstWhere('isoCode', $input['country_code']);
        if (! $country) {
            throw ValidationException::withMessages(['country_code' => 'Selecione um país disponível.']);
        }
        $regionCode = $input['region_code'] ?? '';
        $cityCode = $input['city_code'] ?? '';
        $regionName = trim($input['region_name'] ?? '');
        $cityName = trim($input['city_name'] ?? '');
        $regions = ($regionCode !== '' || $regionName !== '' || $cityCode !== '')
            ? $this->holidays->subdivisions($input['country_code']) : [];
        $region = collect($regions)->firstWhere('code', $regionCode);
        if ($regions && ! $region && ($regionCode !== '' || $regionName !== '')) {
            throw ValidationException::withMessages(['region_code' => 'Selecione uma região disponível.']);
        }
        if (! $regions && $regionCode !== '') {
            throw ValidationException::withMessages(['region_code' => 'A região selecionada é inválida.']);
        }
        $cities = $region && ($cityCode !== '' || $cityName !== '')
            ? $this->holidays->cities($input['country_code'], $region) : [];
        $city = collect($cities)->firstWhere('code', $cityCode);
        if ($cities && ! $city && ($cityCode !== '' || $cityName !== '')) {
            throw ValidationException::withMessages(['city_code' => 'Selecione um município disponível.']);
        }
        if (! $cities && $cityCode !== '') {
            throw ValidationException::withMessages(['city_code' => 'O município selecionado é inválido.']);
        }
        $regionName = $region ? $this->name($region['name']) : $regionName;
        $cityName = $city ? $this->name($city['name']) : $cityName;

        return [[
            'country_code' => $input['country_code'],
            'country_name' => $this->name($country['name']),
            'region_code' => $regionCode,
            'region_name' => $regionName,
            'city_code' => $cityCode,
            'city_name' => $cityName,
        ], (bool) $city];
    }

    public function deleteLocation(Request $request): JsonResponse
    {
        $request->user()->holidayLocation()->delete();

        return response()->json(['data' => null, 'message' => 'Consentimento retirado e localização removida.']);
    }

    public function month(Request $request): JsonResponse
    {
        $input = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
        $location = $request->user()->holidayLocation()->first();
        if (! $location) {
            return response()->json(['message' => 'Autorize o uso da localização para visualizar feriados.'], 403);
        }
        $locationKey = $location->cacheKey();
        $sync = $this->holidays->synchronize($location, (int) $input['year']);
        $currentLocation = $request->user()->holidayLocation()->first();
        if (! $currentLocation || $currentLocation->cacheKey() !== $locationKey) {
            return response()->json(['message' => 'A autorização ou localização foi alterada.'], 403);
        }
        $first = CarbonImmutable::create((int) $input['year'], (int) $input['month'], 1)->startOfDay();
        $items = $sync->holidays()->where('start_date', '<=', $first->endOfMonth()->toDateString())
            ->where('end_date', '>=', $first->toDateString())
            ->orderBy('start_date')->orderBy('name')
            ->get(['start_date', 'end_date', 'name', 'type', 'scope']);

        return response()->json(['data' => [
            'holidays' => $items,
            'status' => $sync->status,
            'source' => $sync->source,
            'synced_at' => $sync->synced_at,
        ]]);
    }

    private function name(array $names): string
    {
        return collect($names)->firstWhere('language', 'PT')['text'] ?? ($names[0]['text'] ?? '');
    }

    private function catalogError(\Throwable $exception): JsonResponse
    {
        Log::warning('Falha ao carregar o catálogo de localidades.', ['error' => $exception->getMessage()]);

        return response()->json(['message' => 'Não foi possível carregar as localidades. Tente novamente.'], 503);
    }
}
