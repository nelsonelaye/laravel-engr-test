<?php

namespace Database\Factories;

use App\Models\Insurer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsurerFactory extends Factory
{
    protected $model = Insurer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Insurance',
            'code' => 'INS-' . strtoupper($this->faker->lexify('????')),
            'min_batch_size' => $this->faker->numberBetween(1, 10),
            'max_batch_size' => $this->faker->numberBetween(50, 200),
            'daily_capacity' => $this->faker->numberBetween(500000, 2000000),
            'preferred_date_type' => $this->faker->randomElement(['encounter', 'submission']),
            'specialty_efficiencies' => [
                'cardiology' => $this->faker->randomFloat(2, 0.8, 1.2),
                'orthopedics' => $this->faker->randomFloat(2, 0.8, 1.2),
                'neurology' => $this->faker->randomFloat(2, 0.8, 1.2),
                'pediatrics' => $this->faker->randomFloat(2, 0.8, 1.2),
                'general_practice' => 1.0,
            ],
        ];
    }
}
