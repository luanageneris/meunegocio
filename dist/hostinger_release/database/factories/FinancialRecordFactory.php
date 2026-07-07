<?php

namespace Database\Factories;

use App\Models\FinancialRecord;
use App\Models\Company;
use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialRecord>
 */
class FinancialRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\FinancialRecord>
     */
    protected $model = FinancialRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->sentence(),
            'entry_type_id' => null,
            'exit_type_id' => null,
            'service_type_id' => null,
            'collaborator_id' => null,
        ];
    }
}
