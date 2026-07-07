<?php

namespace Database\Factories;

use App\Models\DailyClosure;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyClosure>
 */
class DailyClosureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\DailyClosure>
     */
    protected $model = DailyClosure::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total_entry = $this->faker->randomFloat(2, 100, 2000);
        $total_exit = $this->faker->randomFloat(2, 50, $total_entry);
        $profit = $total_entry - $total_exit;

        return [
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'total_entry' => $total_entry,
            'total_exit' => $total_exit,
            'profit' => $profit,
            'is_closed' => true,
        ];
    }
}
