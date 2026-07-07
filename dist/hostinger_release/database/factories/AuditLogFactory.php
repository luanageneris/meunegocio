<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $models = [
            'App\Models\Collaborator',
            'App\Models\FinancialRecord',
            'App\Models\Commission',
            'App\Models\WorkShift',
            'App\Models\ServiceType',
            'App\Models\EntryType',
            'App\Models\ExitType',
            'App\Models\DailyClosure',
            'App\Models\Balance',
        ];

        $actions = ['create', 'update', 'delete', 'restore'];

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement($actions),
            'model' => $this->faker->randomElement($models),
            'model_id' => $this->faker->numberBetween(1, 1000),
            'old_values' => null,
            'new_values' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ],
            'changes' => [
                'name' => [
                    'old' => $this->faker->name(),
                    'new' => $this->faker->name(),
                ],
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'endpoint' => $this->faker->url(),
        ];
    }

    /**
     * State for create action
     */
    public function asCreate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'create',
                'old_values' => null,
                'changes' => null,
            ];
        });
    }

    /**
     * State for update action
     */
    public function asUpdate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'update',
                'old_values' => [
                    'name' => 'Old Name',
                    'email' => 'old@example.com',
                ],
                'changes' => [
                    'name' => [
                        'old' => 'Old Name',
                        'new' => 'New Name',
                    ],
                ],
            ];
        });
    }

    /**
     * State for delete action
     */
    public function asDelete(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'delete',
                'new_values' => null,
                'changes' => null,
            ];
        });
    }
}
