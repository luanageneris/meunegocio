<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\CompanyDefaultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_types_require_replacement_and_move_active_and_deleted_records(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));

        foreach ([
            [EntryType::class, 'entry-types', 'entry_type_id'],
            [ExitType::class, 'exit-types', 'exit_type_id'],
            [ServiceType::class, 'service-types', 'service_type_id'],
        ] as [$model, $endpoint, $column]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $replacement = $model::factory()->create(['company_id' => $company->id]);
            $records = FinancialRecord::factory()->count(2)->create([
                'company_id' => $company->id, 'type' => 'entry', $column => $item->id,
            ]);
            $records[1]->delete();

            $this->deleteJson("/api/personalizar/{$endpoint}/{$item->id}")
                ->assertStatus(409)->assertJsonPath('code', 'replacement_required');
            $this->assertDatabaseHas('financial_records', ['id' => $records[0]->id, $column => $item->id]);

            $this->deleteJson("/api/personalizar/{$endpoint}/{$item->id}", ['replacement_id' => $replacement->id])
                ->assertOk();
            foreach ($records as $record) {
                $this->assertDatabaseHas('financial_records', ['id' => $record->id, $column => $replacement->id]);
            }
            $this->assertSoftDeleted($item);
        }
    }

    public function test_collaborator_replacement_moves_all_associations(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));
        $item = Collaborator::factory()->create(['company_id' => $company->id]);
        $replacement = Collaborator::factory()->create(['company_id' => $company->id]);
        $record = FinancialRecord::factory()->create(['company_id' => $company->id, 'type' => 'entry', 'collaborator_id' => $item->id]);
        $commission = Commission::factory()->create(['company_id' => $company->id, 'collaborator_id' => $item->id]);
        $shift = WorkShift::factory()->create(['company_id' => $company->id, 'collaborator_id' => $item->id]);

        $this->deleteJson("/api/personalizar/collaborators/{$item->id}")->assertStatus(409);
        $this->deleteJson("/api/personalizar/collaborators/{$item->id}", ['replacement_id' => $replacement->id])->assertOk();
        foreach (['financial_records' => $record, 'commissions' => $commission, 'work_shifts' => $shift] as $table => $row) {
            $this->assertDatabaseHas($table, ['id' => $row->id, 'collaborator_id' => $replacement->id]);
        }
    }

    public function test_replacement_must_be_another_active_item_in_the_same_company(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));
        $item = ExitType::factory()->create(['company_id' => $company->id]);
        FinancialRecord::factory()->create(['company_id' => $company->id, 'type' => 'exit', 'exit_type_id' => $item->id]);
        $other = ExitType::factory()->create();
        $inactive = ExitType::factory()->create(['company_id' => $company->id]);
        $inactive->forceFill(['active' => false])->save();
        $deleted = ExitType::factory()->create(['company_id' => $company->id]);
        $deleted->delete();

        foreach ([$item->id, $other->id, $inactive->id, $deleted->id] as $id) {
            $this->deleteJson("/api/personalizar/exit-types/{$item->id}", ['replacement_id' => $id])
                ->assertUnprocessable()->assertJsonValidationErrors('replacement_id');
        }
        $this->assertDatabaseHas('exit_types', ['id' => $item->id, 'deleted_at' => null]);
    }

    public function test_colliding_shifts_prevent_replacement_without_partial_changes(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));
        $item = Collaborator::factory()->create(['company_id' => $company->id]);
        $replacement = Collaborator::factory()->create(['company_id' => $company->id]);
        $record = FinancialRecord::factory()->create(['company_id' => $company->id, 'type' => 'entry', 'collaborator_id' => $item->id]);
        WorkShift::factory()->create(['company_id' => $company->id, 'collaborator_id' => $item->id, 'date' => '2026-01-02']);
        $shift = WorkShift::factory()->create(['company_id' => $company->id, 'collaborator_id' => $replacement->id, 'date' => '2026-01-02']);
        $shift->delete();

        $this->deleteJson("/api/personalizar/collaborators/{$item->id}", ['replacement_id' => $replacement->id])
            ->assertUnprocessable()->assertJsonValidationErrors('replacement_id');
        $this->assertDatabaseHas('financial_records', ['id' => $record->id, 'collaborator_id' => $item->id]);
        $this->assertDatabaseHas('collaborators', ['id' => $item->id, 'deleted_at' => null]);
    }

    public function test_sleeping_items_are_hidden_from_forms_but_can_be_managed_and_reactivated(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));
        foreach ([
            [EntryType::class, 'entry-types'],
            [ExitType::class, 'exit-types'],
            [ServiceType::class, 'service-types'],
            [Collaborator::class, 'collaborators'],
        ] as [$model, $endpoint]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $this->patchJson("/api/personalizar/{$endpoint}/{$item->id}/status", ['active' => false])->assertOk();
            $this->getJson("/api/personalizar/{$endpoint}?include_defaults=1")->assertJsonMissing(['id' => $item->id]);
            $this->getJson("/api/personalizar/{$endpoint}?include_inactive=1")->assertJsonFragment(['id' => $item->id]);
            $this->patchJson("/api/personalizar/{$endpoint}/{$item->id}/status", ['active' => true])->assertOk();
            $this->getJson("/api/personalizar/{$endpoint}")->assertJsonFragment(['id' => $item->id]);
        }
    }

    public function test_status_changes_are_company_scoped_and_require_company_owner_or_root(): void
    {
        $company = Company::factory()->create();
        $item = EntryType::factory()->create(['company_id' => $company->id]);
        $this->actingAs(User::factory()->root()->create(['company_id' => Company::factory()->create()->id]))
            ->patchJson("/api/personalizar/entry-types/{$item->id}/status", ['active' => false])->assertNotFound();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $otherOwner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);
        $this->actingAs($otherOwner)
            ->patchJson("/api/personalizar/entry-types/{$item->id}/status", ['active' => false])->assertForbidden();
        $this->actingAs($owner)
            ->patchJson("/api/personalizar/entry-types/{$item->id}/status", ['active' => false])->assertOk();
        $this->assertFalse($item->fresh()->active);
        $this->patchJson("/api/personalizar/entry-types/{$item->id}/status", ['active' => true])->assertOk();
        $this->assertTrue($item->fresh()->active);
    }

    public function test_owner_can_change_status_for_all_master_data(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        foreach ([
            [EntryType::class, 'entry-types'],
            [ExitType::class, 'exit-types'],
            [ServiceType::class, 'service-types'],
            [Collaborator::class, 'collaborators'],
        ] as [$model, $endpoint]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $this->patchJson("/api/personalizar/{$endpoint}/{$item->id}/status", ['active' => false])->assertOk();
            $this->assertFalse($item->fresh()->active);
            $this->patchJson("/api/personalizar/{$endpoint}/{$item->id}/status", ['active' => true])->assertOk();
            $this->assertTrue($item->fresh()->active);
        }
    }

    public function test_company_owner_can_replace_and_remove_used_master_data_and_restore_it(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        foreach ([
            [EntryType::class, 'entry-types', 'entry_type_id', 'entry'],
            [ExitType::class, 'exit-types', 'exit_type_id', 'exit'],
            [ServiceType::class, 'service-types', 'service_type_id', 'entry'],
            [Collaborator::class, 'collaborators', 'collaborator_id', 'entry'],
        ] as [$model, $endpoint, $column, $type]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $replacement = $model::factory()->create(['company_id' => $company->id]);
            $record = FinancialRecord::factory()->create([
                'company_id' => $company->id,
                'type' => $type,
                $column => $item->id,
            ]);

            $this->deleteJson("/api/personalizar/{$endpoint}/{$item->id}")
                ->assertStatus(409)->assertJsonPath('code', 'replacement_required')
                ->assertJsonPath('message', 'Este item está em uso. Escolha um substituto para os registros ou coloque o item para dormir.');
            $this->assertDatabaseHas('financial_records', ['id' => $record->id, $column => $item->id]);
            $this->deleteJson("/api/personalizar/{$endpoint}/{$item->id}", ['replacement_id' => $replacement->id])
                ->assertOk();
            $this->assertSoftDeleted($item);
            $this->assertDatabaseHas('financial_records', ['id' => $record->id, $column => $replacement->id]);

            $this->postJson("/api/personalizar/{$endpoint}/{$item->id}/restore")->assertOk();
            $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'deleted_at' => null]);
        }
    }

    public function test_only_company_owner_or_root_can_remove_or_restore_master_data(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $otherUser = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);

        foreach ([
            [EntryType::class, 'entry-types'],
            [ExitType::class, 'exit-types'],
            [ServiceType::class, 'service-types'],
            [Collaborator::class, 'collaborators'],
        ] as [$model, $endpoint]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $this->actingAs($otherUser)->deleteJson("/api/personalizar/{$endpoint}/{$item->id}")->assertForbidden();
            $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'deleted_at' => null]);
            $this->actingAs($owner)->deleteJson("/api/personalizar/{$endpoint}/{$item->id}")->assertOk();
            $this->actingAs($otherUser)->postJson("/api/personalizar/{$endpoint}/{$item->id}/restore")->assertForbidden();
            $this->assertSoftDeleted($item);
            $this->actingAs($owner)->postJson("/api/personalizar/{$endpoint}/{$item->id}/restore")->assertOk();
        }
    }

    public function test_only_owner_or_root_can_edit_all_master_data(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $otherUser = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);

        foreach ([
            [EntryType::class, 'entry-types'],
            [ExitType::class, 'exit-types'],
            [ServiceType::class, 'service-types'],
            [Collaborator::class, 'collaborators'],
        ] as [$model, $endpoint]) {
            $item = $model::factory()->create(['company_id' => $company->id]);
            $payload = ['name' => 'Nome atualizado'];
            if ($item instanceof ServiceType) {
                $payload += ['unit' => 'un', 'service_value' => 10, 'commission_percentage' => 5];
            } elseif ($item instanceof Collaborator) {
                $payload['cpf'] = $item->cpf;
            }

            $this->actingAs($otherUser)->putJson("/api/personalizar/{$endpoint}/{$item->id}", $payload)
                ->assertForbidden()->assertJsonPath('message', match ($endpoint) {
                    'entry-types' => 'Apenas o proprietário da empresa pode editar tipos de entrada.',
                    'exit-types' => 'Apenas o proprietário da empresa pode editar tipos de saída.',
                    'service-types' => 'Apenas o proprietário da empresa pode editar tipos de serviço.',
                    'collaborators' => 'Apenas o proprietário da empresa pode editar colaboradores.',
                });
            $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'name' => $item->name]);
            $this->actingAs($owner)->putJson("/api/personalizar/{$endpoint}/{$item->id}", $payload)->assertOk();
            $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'name' => 'Nome atualizado']);
        }
    }

    public function test_owner_can_create_service_type(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson('/api/personalizar/service-types', [
            'name' => 'Serviço de teste',
            'unit' => 'un',
            'service_value' => 10,
            'commission_percentage' => 5,
        ])->assertCreated();
    }

    public function test_sole_legacy_owner_can_change_status(): void
    {
        $company = Company::factory()->create(['owner_id' => null]);
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $item = ExitType::factory()->create(['company_id' => $company->id]);

        $this->actingAs($owner)->patchJson("/api/personalizar/exit-types/{$item->id}/status", ['active' => false])
            ->assertOk();
        $this->assertFalse($item->fresh()->active);
    }

    public function test_protected_defaults_cannot_be_put_to_sleep(): void
    {
        $company = Company::factory()->create();
        $defaults = app(CompanyDefaultsService::class)->ensureForCompany($company->id);
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));

        foreach ([
            ['entry-types', $defaults['entry_type']],
            ['exit-types', $defaults['exit_type']],
            ['exit-types', $defaults['commission_exit_type']],
            ['collaborators', $defaults['collaborator']],
        ] as [$endpoint, $item]) {
            $this->patchJson("/api/personalizar/{$endpoint}/{$item->id}/status", ['active' => false])
                ->assertUnprocessable();
            $this->assertTrue((bool) $item->fresh()->active);
        }
    }

    public function test_deleted_records_alone_still_require_a_replacement(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->root()->create(['company_id' => $company->id]));
        $item = ExitType::factory()->create(['company_id' => $company->id]);
        $record = FinancialRecord::factory()->create(['company_id' => $company->id, 'type' => 'exit', 'exit_type_id' => $item->id]);
        $record->delete();

        $this->deleteJson("/api/personalizar/exit-types/{$item->id}")
            ->assertStatus(409)->assertJsonPath('code', 'replacement_required');
    }
}
