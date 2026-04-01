<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Insurer;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'insurer_id' => Insurer::factory(),
            'total_cost' => $this->faker->randomFloat(2, 1000, 100000),
            'total_claims' => $this->faker->numberBetween(5, 50),
            'status' => 'pending',
        ];
    }
}
