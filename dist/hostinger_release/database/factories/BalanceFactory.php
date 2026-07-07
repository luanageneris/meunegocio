<?php

namespace Database\Factories;

use App\Models\Balance;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Balance>
 */
class BalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Balance>
     */
    protected $model = Balance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalance = $this->faker->randomFloat(2, 1000, 10000);
        $closingBalance = $openingBalance + $this->faker->randomFloat(2, -1000, 2000);

        return [
            'company_id' => Company::factory(),
            'reference_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
        ];
    }
}
