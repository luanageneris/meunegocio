<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class HistoryControllerTest extends TestCase
{
    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()
                         ->for($this->company)
                         ->create(['role' => 'root']);
    }

    /** @test */
    public function can_list_audit_logs()
    {
        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->count(5)
                ->create();

        $response = $this->actingAs($this->user)->getJson('/api/history');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.id', AuditLog::first()->id)
                 ->assertJsonPath('pagination.total', 5);
    }

    /** @test */
    public function can_filter_by_action()
    {
        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->create(function () {
                    return [
                        'action' => 'create',
                        'model' => 'App\Models\Collaborator',
                        'model_id' => 1,
                    ];
                });

        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->create(function () {
                    return [
                        'action' => 'update',
                        'model' => 'App\Models\Collaborator',
                        'model_id' => 1,
                    ];
                });

        $response = $this->actingAs($this->user)
                        ->getJson('/api/history?action=create');

        $response->assertStatus(200)
                 ->assertJsonPath('pagination.total', 1)
                 ->assertJsonPath('data.0.action', 'create');
    }

    /** @test */
    public function can_view_log_details()
    {
        $log = AuditLog::factory()
                      ->for($this->company)
                      ->for($this->user)
                      ->create();

        $response = $this->actingAs($this->user)
                        ->getJson("/api/history/{$log->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $log->id)
                 ->assertJsonPath('data.action_label', 'Criado');
    }

    /** @test */
    public function can_get_statistics()
    {
        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->count(3)
                ->create(function () {
                    return [
                        'action' => 'create',
                        'model' => 'App\Models\Collaborator',
                    ];
                });

        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->count(2)
                ->create(function () {
                    return [
                        'action' => 'update',
                        'model' => 'App\Models\FinancialRecord',
                    ];
                });

        $response = $this->actingAs($this->user)
                        ->getJson('/api/history/stats');

        $response->assertStatus(200)
                 ->assertJsonPath('data.total', 5)
                 ->assertJsonPath('data.by_action.create', 3)
                 ->assertJsonPath('data.by_action.update', 2);
    }

    /** @test */
    public function cannot_access_another_company_logs()
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()
                        ->for($otherCompany)
                        ->create(['role' => 'root']);

        AuditLog::factory()
                ->for($otherCompany)
                ->for($otherUser)
                ->create();

        $response = $this->actingAs($this->user)
                        ->getJson('/api/history');

        $response->assertStatus(200)
                 ->assertJsonPath('pagination.total', 0);
    }

    /** @test */
    public function requires_authentication()
    {
        $response = $this->getJson('/api/history');

        $response->assertStatus(401);
    }
}
