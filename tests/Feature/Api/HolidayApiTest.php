<?php

namespace Tests\Feature\Api;

use App\Models\HolidaySync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidayApiTest extends TestCase
{
    use RefreshDatabase;

    private function location(User $user, string $country = 'PT'): void
    {
        $user->holidayLocation()->create([
            'country_code' => $country,
            'country_name' => $country,
            'region_code' => $country === 'PT' ? 'PT-LI' : '',
            'region_name' => $country === 'PT' ? 'Lisboa' : 'São Paulo',
            'city_code' => $country === 'PT' ? 'PT-LI-LI' : '',
            'city_name' => $country === 'PT' ? 'Lisboa' : 'São Paulo',
            'consented_at' => now(),
        ]);
    }

    public function test_consent_is_required_and_revocation_does_not_remove_shared_holidays(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->location($first);
        $this->location($second);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([$this->event('national', true)], 200)]);

        $this->actingAs($first, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertOk()->assertJsonCount(1, 'data.holidays');
        $this->actingAs($first, 'sanctum')->deleteJson('/api/holidays/location')->assertOk();
        $this->actingAs($first, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertForbidden();
        $this->actingAs($second, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertOk()->assertJsonCount(1, 'data.holidays');
        $this->assertDatabaseCount('holiday_syncs', 1);
        $this->assertDatabaseCount('holidays', 1);
        Http::assertSentCount(1);
    }

    public function test_filters_by_location_and_month_and_preserves_multiple_events(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([
            $this->event('national', true),
            $this->event('local', false, ['PT-LI-LI'], 'Optional'),
            $this->event('other', false, ['PT-PO-PO']),
            array_merge($this->event('february', true), ['startDate' => '2026-02-01', 'endDate' => '2026-02-01']),
        ], 200)]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1]);
        $response->assertOk()->assertJsonCount(2, 'data.holidays')
            ->assertJsonFragment(['name' => 'local', 'type' => 'optional', 'scope' => 'local'])
            ->assertJsonMissing(['name' => 'other']);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 2])
            ->assertOk()->assertJsonCount(1, 'data.holidays')->assertJsonFragment(['name' => 'february']);
        Http::assertSentCount(1);
    }

    public function test_brazil_fallback_is_partial_and_does_not_invent_classification(): void
    {
        $user = User::factory()->create();
        $this->location($user, 'BR');
        Http::fake([
            'openholidaysapi.org/PublicHolidays*' => Http::response([], 200),
            'brasilapi.com.br/api/feriados/v1/*' => Http::response([['date' => '2026-02-17', 'name' => 'Carnaval', 'type' => 'national']], 200),
        ]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 2])
            ->assertOk()->assertJsonPath('data.status', 'partial')
            ->assertJsonFragment(['name' => 'Carnaval', 'type' => 'unknown', 'scope' => 'national']);
    }

    public function test_failed_refresh_keeps_previously_saved_data(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::sequence()
            ->push([$this->event('national', true)], 200)
            ->push([], 200)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertOk();
        HolidaySync::query()->update(['synced_at' => now()->subDays(366), 'attempted_at' => now()->subDays(366)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonPath('data.status', 'stale')->assertJsonCount(1, 'data.holidays');
    }

    public function test_expired_data_is_refreshed_after_365_days(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::sequence()
            ->push([$this->event('antigo', true)], 200)
            ->push([$this->event('atualizado', true)], 200)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertOk();
        $this->travel(365)->days();
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonFragment(['name' => 'atualizado'])->assertJsonMissing(['name' => 'antigo']);
        Http::assertSentCount(2);
    }

    public function test_empty_response_is_reported_as_unavailable_not_a_confirmed_empty_year(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([], 200)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonCount(0, 'data.holidays');
    }

    public function test_invalid_provider_dates_do_not_replace_saved_data(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::sequence()
            ->push([$this->event('válido', true)], 200)
            ->push([array_merge($this->event('inválido', true), ['startDate' => '2026-02-31'])], 200)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertOk();
        $this->travel(365)->days();
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonPath('data.status', 'stale')->assertJsonFragment(['name' => 'válido']);
    }

    public function test_a_holiday_spanning_months_is_returned_for_each_month(): void
    {
        $user = User::factory()->create();
        $this->location($user);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([
            array_merge($this->event('intervalo', true), ['startDate' => '2026-01-31', 'endDate' => '2026-02-02']),
        ])]);
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])->assertJsonCount(1, 'data.holidays');
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 2])->assertJsonCount(1, 'data.holidays');
    }

    public function test_location_requires_consent_and_valid_catalog_selection(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'openholidaysapi.org/Countries*' => Http::response([['isoCode' => 'PT', 'name' => [['language' => 'PT', 'text' => 'Portugal']]]]),
            'openholidaysapi.org/Subdivisions*' => Http::response([[
                'code' => 'PT-LI', 'name' => [['language' => 'PT', 'text' => 'Lisboa']],
                'children' => [['code' => 'PT-LI-LI', 'name' => [['language' => 'PT', 'text' => 'Lisboa']]]],
            ]]),
        ]);
        $input = ['country_code' => 'PT', 'region_code' => 'PT-LI', 'city_code' => 'PT-LI-LI'];
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input)->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input + ['consent' => true])
            ->assertOk()->assertJsonPath('data.city_name', 'Lisboa');
        $this->assertDatabaseHas('user_holiday_locations', ['user_id' => $user->id, 'country_code' => 'PT']);
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', array_merge($input, ['city_code' => 'PT-OTHER', 'consent' => true]))->assertUnprocessable();
    }

    public function test_brazilian_location_is_verified_before_saving_even_with_no_holidays_in_the_month(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'openholidaysapi.org/Countries*' => Http::response([['isoCode' => 'BR', 'name' => [['language' => 'PT', 'text' => 'Brasil']]]]),
            'brasilapi.com.br/api/ibge/uf/v1' => Http::response([
                ['id' => 35, 'sigla' => 'SP', 'nome' => 'São Paulo'],
                ['id' => 33, 'sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
            ]),
            'raw.githubusercontent.com/kelvins/Municipios-Brasileiros/*' => Http::response("\xEF\xBB\xBF".json_encode([
                ['codigo_ibge' => 3550308, 'nome' => 'São Paulo', 'codigo_uf' => 35],
                ['codigo_ibge' => 3304557, 'nome' => 'Rio de Janeiro', 'codigo_uf' => 33],
            ])),
            'openholidaysapi.org/PublicHolidays*' => Http::response([
                array_merge($this->event('fevereiro', true), ['startDate' => '2026-02-01', 'endDate' => '2026-02-01']),
            ]),
        ]);
        $input = ['country_code' => 'BR', 'region_code' => 'BR-SP', 'city_code' => 'IBGE-3550308', 'consent' => true];
        $this->actingAs($user, 'sanctum')->getJson('/api/holidays/cities?country=BR&region=BR-SP')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'IBGE-3550308');
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/preview', $input + ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonPath('data.city_verified', true)->assertJsonPath('data.holiday_count', 0);
        $this->assertDatabaseMissing('user_holiday_locations', ['user_id' => $user->id]);
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input)
            ->assertOk()->assertJsonPath('data.city_name', 'São Paulo');
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/preview', array_merge($input, [
            'city_code' => 'IBGE-3304557', 'year' => 2026, 'month' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('city_code');
    }

    public function test_country_only_and_region_only_locations_show_matching_holidays(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'openholidaysapi.org/Countries*' => Http::response([['isoCode' => 'PT', 'name' => [['language' => 'PT', 'text' => 'Portugal']]]]),
            'openholidaysapi.org/Subdivisions*' => Http::response([[
                'code' => 'PT-LI', 'name' => [['language' => 'PT', 'text' => 'Lisboa']],
                'children' => [['code' => 'PT-LI-LI', 'name' => [['language' => 'PT', 'text' => 'Lisboa']]]],
            ]]),
            'openholidaysapi.org/PublicHolidays*' => Http::response([
                $this->event('nacional', true),
                $this->event('regional', false, ['PT-LI']),
                $this->event('municipal', false, ['PT-LI-LI']),
            ]),
        ]);
        $input = ['country_code' => 'PT', 'consent' => true];
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/preview', $input + ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonPath('data.city_verified', false)->assertJsonPath('data.holiday_count', 1);
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input)
            ->assertOk()->assertJsonPath('data.region_name', '')->assertJsonPath('data.city_name', '');
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonCount(1, 'data.holidays')->assertJsonFragment(['name' => 'nacional']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/Subdivisions'));

        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input + ['region_code' => 'PT-LI'])
            ->assertOk()->assertJsonPath('data.region_name', 'Lisboa')->assertJsonPath('data.city_name', '');
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/month', ['year' => 2026, 'month' => 1])
            ->assertOk()->assertJsonCount(2, 'data.holidays')
            ->assertJsonFragment(['name' => 'regional', 'scope' => 'regional'])
            ->assertJsonMissing(['name' => 'municipal']);
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input + ['city_code' => 'PT-LI-LI'])
            ->assertUnprocessable()->assertJsonValidationErrors('city_code');
    }

    public function test_preview_explains_when_a_manual_city_cannot_be_verified_and_the_provider_fails(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'openholidaysapi.org/Countries*' => Http::response([['isoCode' => 'FR', 'name' => [['language' => 'FR', 'text' => 'France']]]]),
            'openholidaysapi.org/Subdivisions*' => Http::response([]),
            'openholidaysapi.org/PublicHolidays*' => Http::response([], 200),
        ]);
        $input = [
            'country_code' => 'FR', 'region_name' => 'Região', 'city_name' => 'Cidade',
            'consent' => true, 'year' => 2026, 'month' => 1,
        ];
        $this->actingAs($user, 'sanctum')->postJson('/api/holidays/preview', $input)
            ->assertOk()->assertJsonPath('data.city_verified', false)->assertJsonPath('data.status', 'failed');
        $this->actingAs($user, 'sanctum')->putJson('/api/holidays/location', $input)->assertOk();
    }

    private function event(string $name, bool $national, array $codes = [], string $type = 'Public'): array
    {
        return [
            'id' => $name, 'startDate' => '2026-01-01', 'endDate' => '2026-01-01',
            'name' => [['language' => 'PT', 'text' => $name]], 'type' => $type,
            'nationwide' => $national, 'subdivisions' => array_map(fn ($code) => ['code' => $code], $codes),
        ];
    }
}
