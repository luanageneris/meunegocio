<?php

namespace Tests\Feature;

use App\Models\EntryType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->root()->create(['company_id' => $this->company->id]);
    }

    public function test_index_returns_entry_types_for_company()
    {
        EntryType::factory()->count(3)->create(['company_id' => $this->company->id]);
        EntryType::factory()->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/personalizar/entry-types');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_store_creates_entry_type()
    {
        $payload = [
            'name' => 'Vendas',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/personalizar/entry-types', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('entry_types', [
            'name' => 'Vendas',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_validates_required_name()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/entry-types', []);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name', $response->json('errors'));
    }

    public function test_show_returns_entry_type()
    {
        $type = EntryType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/entry-types/{$type->id}");

        $response->assertOk();
        $this->assertEquals($type->id, $response->json('id'));
    }

    public function test_show_denies_access_to_other_company_entry_type()
    {
        $otherCompany = Company::factory()->create();
        $type = EntryType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/entry-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_entry_type()
    {
        $type = EntryType::factory()->create(['company_id' => $this->company->id]);
        $payload = [
            'name' => 'Vendas Atualizadas',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/entry-types/{$type->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('entry_types', [
            'id' => $type->id,
            'name' => 'Vendas Atualizadas',
        ]);
    }

    public function test_update_denies_access_to_other_company_entry_type()
    {
        $otherCompany = Company::factory()->create();
        $type = EntryType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/entry-types/{$type->id}", [
            'name' => 'Updated',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_entry_type()
    {
        $type = EntryType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/entry-types/{$type->id}");

        $response->assertOk();
        $this->assertSoftDeleted('entry_types', ['id' => $type->id]);
    }

    public function test_destroy_denies_access_to_other_company_entry_type()
    {
        $otherCompany = Company::factory()->create();
        $type = EntryType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/entry-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_destroy_requires_root_role()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'role' => 'owner']);
        $type = EntryType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/personalizar/entry-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_access_denied()
    {
        $response = $this->getJson('/api/personalizar/entry-types');

        $response->assertUnauthorized();
    }
}
