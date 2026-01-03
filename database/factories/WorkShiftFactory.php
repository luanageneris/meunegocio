<?php

namespace Database\Factories;

use App\Models\WorkShift;
use App\Models\Company;
use App\Models\Collaborator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkShift>
 */
class WorkShiftFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\WorkShift>
     */
    protected $model = WorkShift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->time('H:i');
        $startHour = (int)explode(':', $startTime)[0];
        $endHour = min($startHour + $this->faker->numberBetween(4, 8), 23);
        $endTime = sprintf('%02d:%02d', $endHour, $this->faker->numberBetween(0, 59));

        // Generate unique dates - each factory call gets a random date
        $daysOffset = $this->faker->numberBetween(-365, 0);

        return [
            'company_id' => Company::factory(),
            'collaborator_id' => Collaborator::factory(),
            'date' => now()->addDays($daysOffset),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_hours' => $this->faker->randomFloat(2, 1, 12),
        ];
    }
}
