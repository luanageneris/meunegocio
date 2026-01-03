<?php

namespace Database\Factories;

use App\Models\Collaborator;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollaboratorFactory extends Factory
{
    protected $model = Collaborator::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'cpf' => $this->faker->numerify('###.###.###-##'),
            'active' => true,
            'company_id' => Company::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
