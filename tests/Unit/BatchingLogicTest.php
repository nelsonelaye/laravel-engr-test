<?php

namespace Tests\Unit;

use App\Actions\BatchClaimsAction;
use App\Models\Claim;
use App\Models\Insurer;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class BatchingLogicTest extends TestCase
{
    protected BatchClaimsAction $batchClaimsAction;
    protected Insurer $insurer;
    protected User $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->batchClaimsAction = app(BatchClaimsAction::class);
        
        $this->provider = User::factory()->create([
            'name' => 'Test Provider',
            'email' => 'provider-' . uniqid() . '@test.local',
        ]);

        $this->insurer = Insurer::create([
            'name' => 'Test Insurer',
            'code' => 'TST-' . uniqid(),
            'min_batch_size' => 1,
            'max_batch_size' => 100,
            'daily_capacity' => 1000000,
            'preferred_date_type' => 'encounter',
            'specialty_efficiencies' => [
                'cardiology' => 0.9,
                'orthopedics' => 1.1,
                'neurology' => 1.2,
                'pediatrics' => 0.85,
                'general_practice' => 1.0,
            ],
        ]);
    }

    /** @test */
    public function test_day_factor_increases_from_20_percent_on_day_1_to_50_percent_on_day_30()
    {
        // Using reflection to test private method
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        $claim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'general_practice',
            'priority_level' => 3,
            'total_amount' => 10000,
        ]);

        // Day 1: base * 0.2 * 1.0 * 1.24 * 1.02 ≈ 25.30
        $day1Cost = $method->invoke($this->batchClaimsAction, $claim, $this->insurer, Carbon::createFromDate(2026, 4, 1));
        
        // Day 30: base * 0.5 * 1.0 * 1.24 * 1.02 ≈ 63.25
        $day30Cost = $method->invoke($this->batchClaimsAction, $claim, $this->insurer, Carbon::createFromDate(2026, 4, 30));

        // Day 30 should be > Day 1
        $this->assertGreaterThan($day1Cost, $day30Cost);
        
        // Day 30 should be roughly 2.5x Day 1 (50% / 20%)
        // The exact ratio depends on specialty multiplier matching, so allow wider range
        $ratio = $day30Cost / $day1Cost;
        $this->assertGreaterThan(2.3, $ratio);
        $this->assertLessThan(2.6, $ratio);
    }

    /** @test */
    public function test_specialty_multiplier_applies_correctly()
    {
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        $date = Carbon::createFromDate(2026, 4, 15);

        // Cardiology: 0.9 multiplier (cheaper)
        $cardiologyClaim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 10000,
        ]);
        $cardiologyCost = $method->invoke($this->batchClaimsAction, $cardiologyClaim, $this->insurer, $date);

        // Neurology: 1.2 multiplier (expensive)
        $neurologyClaim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'Neurology',
            'priority_level' => 3,
            'total_amount' => 10000,
        ]);
        $neurologyCost = $method->invoke($this->batchClaimsAction, $neurologyClaim, $this->insurer, $date);

        // Neurology should cost more than Cardiology
        $this->assertGreaterThan($cardiologyCost, $neurologyCost);
        
        // Ratio should be approximately 1.2 / 0.9 ≈ 1.33
        $ratio = $neurologyCost / $cardiologyCost;
        $this->assertGreaterThan(1.3, $ratio);
        $this->assertLessThan(1.36, $ratio);
    }

    /** @test */
    public function test_priority_multiplier_scales_with_priority_level()
    {
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        $date = Carbon::createFromDate(2026, 4, 15);

        // Priority 1
        $priority1Claim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'General Practice',
            'priority_level' => 1,
            'total_amount' => 10000,
        ]);
        $priority1Cost = $method->invoke($this->batchClaimsAction, $priority1Claim, $this->insurer, $date);

        // Priority 5
        $priority5Claim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'General Practice',
            'priority_level' => 5,
            'total_amount' => 10000,
        ]);
        $priority5Cost = $method->invoke($this->batchClaimsAction, $priority5Claim, $this->insurer, $date);

        // Priority 5 should cost more than Priority 1
        $this->assertGreaterThan($priority1Cost, $priority5Cost);
        
        // Ratio should be approximately 1.4 / 1.08 ≈ 1.30
        $ratio = $priority5Cost / $priority1Cost;
        $this->assertGreaterThan(1.29, $ratio);
        $this->assertLessThan(1.31, $ratio);
    }

    /** @test */
    public function test_monetary_multiplier_scales_with_claim_value()
    {
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        $date = Carbon::createFromDate(2026, 4, 15);

        // Small claim: ₦10,000
        $smallClaim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'General Practice',
            'priority_level' => 3,
            'total_amount' => 10000,
        ]);
        $smallCost = $method->invoke($this->batchClaimsAction, $smallClaim, $this->insurer, $date);

        // Large claim: ₦500,000
        $largeClaim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'General Practice',
            'priority_level' => 3,
            'total_amount' => 500000,
        ]);
        $largeCost = $method->invoke($this->batchClaimsAction, $largeClaim, $this->insurer, $date);

        // Larger claim should cost more
        $this->assertGreaterThan($smallCost, $largeCost);
    }

    /** @test */
    public function test_zero_claim_amount_is_handled()
    {
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        $date = Carbon::createFromDate(2026, 4, 15);

        $zeroClaim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'General Practice',
            'priority_level' => 3,
            'total_amount' => 0,
        ]);

        // Should not throw error, cost should be base * day * specialty * priority * 1.0
        $cost = $method->invoke($this->batchClaimsAction, $zeroClaim, $this->insurer, $date);
        $this->assertIsNumeric($cost);
        $this->assertGreaterThan(0, $cost);
    }

    /** @test */
    public function test_final_cost_calculation_example()
    {
        $reflection = new \ReflectionClass($this->batchClaimsAction);
        $method = $reflection->getMethod('calculateProcessingCost');
        $method->setAccessible(true);

        // Example: Day 15, Cardiology, Priority 3, ₦50,000
        $claim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 50000,
        ]);

        $date = Carbon::createFromDate(2026, 4, 15);
        $cost = $method->invoke($this->batchClaimsAction, $claim, $this->insurer, $date);

        // Expected: 100 * 0.35 * 0.9 * 1.24 * 1.10 ≈ 42.78
        $this->assertGreaterThan(42, $cost);
        $this->assertLessThan(44, $cost);
    }
}
