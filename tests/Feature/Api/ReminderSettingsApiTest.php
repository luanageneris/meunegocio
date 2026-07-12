<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\UserReminderSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;

    protected function setUp(): void
    {
        parent::setUp();

        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);

        $this->company = Company::create([
            'name' => 'Reminder Company ' . $ts,
            'slug' => 'reminder-company-' . $ts,
            'email' => 'reminder+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-97',
            'address' => 'Test Address',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root Reminder',
            'email' => 'luanacarvalhomesquita@gmail.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-reminder-' . $ts,
            'role' => 'root',
        ]);
    }

    public function test_can_get_default_reminder_settings(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/company/reminder-settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.daily_enabled', false)
            ->assertJsonPath('data.monthly_enabled', false)
            ->assertJsonPath('data.yearly_enabled', false)
            ->assertJsonPath('data.send_time', '18:00');

        $this->assertDatabaseHas('user_reminder_settings', [
            'user_id' => $this->rootUser->id,
            'daily_enabled' => false,
            'monthly_enabled' => false,
            'yearly_enabled' => false,
        ]);
    }

    public function test_can_update_reminder_settings_and_pin(): void
    {
        UserReminderSetting::create([
            'user_id' => $this->rootUser->id,
            'daily_enabled' => false,
            'daily_condition' => UserReminderSetting::CONDITION_MISSING_ENTRY_OR_EXIT,
            'daily_days_of_week' => [1, 2, 3],
            'send_time' => '18:00',
            'monthly_enabled' => false,
            'yearly_enabled' => false,
            'pdf_pin' => 'A1B2',
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson('/api/company/reminder-settings', [
                'daily_enabled' => true,
                'daily_condition' => UserReminderSetting::CONDITION_MISSING_BOTH,
                'daily_days_of_week' => [1, 3, 5],
                'send_time' => '09:30',
                'monthly_enabled' => true,
                'yearly_enabled' => true,
                'pdf_pin' => 'Z9X8',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.daily_enabled', true)
            ->assertJsonPath('data.daily_condition', UserReminderSetting::CONDITION_MISSING_BOTH)
            ->assertJsonPath('data.send_time', '09:30')
            ->assertJsonPath('data.monthly_enabled', true)
            ->assertJsonPath('data.yearly_enabled', true);

        $setting = UserReminderSetting::query()->where('user_id', $this->rootUser->id)->firstOrFail();

        $this->assertSame([1, 3, 5], $setting->daily_days_of_week);
        $this->assertTrue($setting->daily_enabled);
        $this->assertTrue($setting->monthly_enabled);
        $this->assertTrue($setting->yearly_enabled);
        $this->assertSame('09:30:00', $setting->send_time);
        $this->assertSame('Z9X8', $setting->pdf_pin);
    }

    public function test_validation_rejects_invalid_pin(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson('/api/company/reminder-settings', [
                'daily_enabled' => true,
                'daily_condition' => UserReminderSetting::CONDITION_MISSING_ENTRY,
                'daily_days_of_week' => [1],
                'send_time' => '08:00',
                'monthly_enabled' => false,
                'yearly_enabled' => false,
                'pdf_pin' => '***',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pdf_pin');
    }
}
