<?php

namespace Database\Factories;

use App\Models\ExitType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExitTypeFactory extends Factory
{
    protected $model = ExitType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'company_id' => Company::factory(),
        ];
    }
}
