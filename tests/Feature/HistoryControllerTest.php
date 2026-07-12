<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function can_list_audit_logs()
    {
        AuditLog::factory()
                ->for($this->company)
                ->for($this->user)
                ->count(5)
                ->create();

        $response = $this->actingAs($this->user)->getJson('/api/history');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data')
                 ->assertJsonPath('pagination.total', 5);
    }

    #[Test]
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

    #[Test]
    public function can_view_log_details()
    {
        $log = AuditLog::factory()
                      ->for($this->company)
                      ->for($this->user)
                      ->create(['action' => 'create']);

        $response = $this->actingAs($this->user)
                        ->getJson("/api/history/{$log->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $log->id)
                 ->assertJsonPath('data.action_label', 'Criado');
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function requires_authentication()
    {
        $response = $this->getJson('/api/history');

        $response->assertStatus(401);
    }

    #[Test]
    public function company_update_is_audited_with_field_level_changes()
    {
        $unique = (string) (time() . random_int(1000, 9999));

        $response = $this->actingAs($this->user, 'sanctum')->putJson('/api/company', [
            'name' => 'Empresa Atualizada Teste ' . $unique,
            'email' => 'empresa.teste+' . $unique . '@example.com',
            'phone' => '11999999999',
            'document' => str_pad(substr($unique, 0, 14), 14, '0'),
            'address' => 'Rua Auditoria, 123',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'postal_code' => '01001000',
            'use_service_types' => true,
        ]);

        $response->assertOk();

        $log = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('model', Company::class)
            ->where('action', 'update')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->company->id, $log->model_id);
        $this->assertIsArray($log->changes);
        $this->assertArrayHasKey('name', $log->changes);
        $this->assertSame('Empresa Atualizada Teste ' . $unique, $log->changes['name']['new']);
        $this->assertArrayNotHasKey('endpoint', $log->changes);

        $historyResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/history');

        $historyResponse->assertOk()
            ->assertJsonPath('data.0.model', Company::class)
            ->assertJsonPath('data.0.model_id', $this->company->id)
            ->assertJsonPath('data.0.details.type', 'field_changes');
    }
}
