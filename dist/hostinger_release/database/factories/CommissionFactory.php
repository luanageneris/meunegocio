<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\Company;
use App\Models\Collaborator;
use App\Models\FinancialRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commission>
 */
class CommissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Commission>
     */
    protected $model = Commission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'collaborator_id' => Collaborator::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'financial_record_id' => null,
            'paid_at' => null,
            'notes' => $this->faker->sentence(),
        ];
    }
}

