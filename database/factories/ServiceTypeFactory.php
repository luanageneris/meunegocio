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
            'commission_percentage' => $this->faker->numberBetween(5, 30),
            'company_id' => Company::factory(),
        ];
    }
}
