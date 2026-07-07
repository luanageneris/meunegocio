<?php

namespace Database\Factories;

use App\Models\ServiceType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'unit' => $this->faker->randomElement(['Folha', 'Hora', 'Serviço', 'Unidade', 'Metro']),
            'service_value' => $this->faker->numberBetween(100, 5000) / 100,
            'commission_percentage' => $this->faker->numberBetween(5, 30),
            'commission_value' => $this->faker->numberBetween(50, 1000) / 100,
            'resource_value' => $this->faker->numberBetween(0, 500) / 100,
            'company_id' => Company::factory(),
        ];
    }
}
