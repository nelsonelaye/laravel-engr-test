<?php

namespace Database\Factories;

use App\Models\ClaimItem;
use App\Models\Claim;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClaimItemFactory extends Factory
{
    protected $model = ClaimItem::class;

    public function definition(): array
    {
        return [
            'claim_id' => Claim::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit_price' => $this->faker->numberBetween(1000, 50000),
        ];
    }
}
