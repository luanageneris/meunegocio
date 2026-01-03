<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Collaborator;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkShiftApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;
    protected Collaborator $collaborator;

    protected function setUp(): void
    {
        parent::setUp();
        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);

        $this->company = Company::create([
            'name' => 'Test Company ' . $ts,
            'slug' => 'test-company-' . $ts,
            'email' => 'test+' . $ts . '@example.com',
            'document' => $ts . '.345.678/0001-90',
            'website' => 'https://example.com',
            'address' => 'Rua Teste, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User',
            'email' => 'root+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'role' => 'root',
        ]);

        $this->collaborator = Collaborator::factory()->for($this->company)->create();
    }

    public function test_can_list_work_shifts(): void
    {
        WorkShift::factory(5)->for($this->company)->for($this->collaborator, 'collaborator')->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/work-shifts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'company_id', 'date', 'start_time', 'end_time']
                ]
            ]);
    }

    public function test_can_create_work_shift(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/work-shifts', [
                'date' => '2025-01-15',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'collaborator_id' => $this->collaborator->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_shifts', [
            'collaborator_id' => $this->collaborator->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_update_work_shift(): void
    {
        $shift = WorkShift::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create([
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/registros/work-shifts/{$shift->id}", [
                'date' => $shift->date,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'collaborator_id' => $this->collaborator->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('work_shifts', [
            'id' => $shift->id,
            'start_time' => '09:00',
        ]);
    }

    public function test_can_delete_work_shift(): void
    {
        $shift = WorkShift::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/registros/work-shifts/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Turno removido com sucesso');

        $this->assertSoftDeleted('work_shifts', ['id' => $shift->id]);
    }

    public function test_can_get_shifts_by_date(): void
    {
        $date = '2025-01-15';
        // Create 3 different collaborators with shifts on same date
        $collaborators = Collaborator::factory(3)->for($this->company)->create();
        foreach ($collaborators as $collaborator) {
            WorkShift::factory()
                ->for($this->company)
                ->for($collaborator, 'collaborator')
                ->create(['date' => $date]);
        }

        // Create 2 collaborators with shifts on different date
        $collaborators2 = Collaborator::factory(2)->for($this->company)->create();
        foreach ($collaborators2 as $collaborator) {
            WorkShift::factory()
                ->for($this->company)
                ->for($collaborator, 'collaborator')
                ->create(['date' => '2025-01-16']);
        }

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/work-shifts/by-date/{$date}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/work-shifts', [
                'date' => '2025-01-15',
                'start_time' => '14:00',
                'end_time' => '08:00',
                'collaborator_id' => $this->collaborator->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.end_time', ['A hora de término deve ser após a hora de início']);
    }
}
