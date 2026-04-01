<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\Insurer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ClaimFactory extends Factory
{
    protected $model = Claim::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'insurer_id' => Insurer::factory(),
            'batch_id' => null,
            'provider_name' => $this->faker->company(),
            'encounter_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
            'submission_date' => Carbon::now()->format('Y-m-d'),
            'specialty' => $this->faker->randomElement(['Cardiology', 'Orthopedics', 'Neurology', 'Pediatrics', 'General Practice']),
            'priority_level' => $this->faker->numberBetween(1, 5),
            'total_amount' => $this->faker->numberBetween(10000, 500000),
            'processing_cost' => $this->faker->randomFloat(2, 10, 500),
            'status' => 'pending',
        ];
    }
}
