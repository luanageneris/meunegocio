<?php

namespace Database\Factories;

use App\Models\EntryType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryTypeFactory extends Factory
{
    protected $model = EntryType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'requires_collaborator' => $this->faker->boolean(),
            'company_id' => Company::factory(),
        ];
    }
}
