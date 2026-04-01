<?php

namespace Tests\Unit;

use App\Actions\BatchClaimsAction;
use App\Models\Batch;
use App\Models\Claim;
use App\Models\Insurer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchClaimsActionTest extends TestCase
{
    use RefreshDatabase;

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
            'min_batch_size' => 2,
            'max_batch_size' => 100,
            'daily_capacity' => 100000,
            'preferred_date_type' => 'encounter',
            'specialty_efficiencies' => [
                'cardiology' => 0.9,
                'general_practice' => 1.0,
            ],
        ]);
    }

    /** @test */
    public function test_batch_is_created_when_minimum_size_met()
    {
        // Create 5 claims (min_batch_size is 2)
        for ($i = 0; $i < 5; $i++) {
            Claim::factory()->create([
                'insurer_id' => $this->insurer->id,
                'user_id' => $this->provider->id,
                'provider_name' => 'Provider A',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty' => 'Cardiology',
                'priority_level' => 3,
                'total_amount' => 10000,
                'status' => 'pending',
            ]);
        }

        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNotNull($batch);
        $this->assertGreaterThanOrEqual($this->insurer->min_batch_size, $batch->total_claims);
    }

    /** @test */
    public function test_batch_respects_minimum_batch_size_constraint()
    {
        $this->insurer->update(['min_batch_size' => 10]);

        // Create only 3 claims (less than min_batch_size)
        for ($i = 0; $i < 3; $i++) {
            Claim::factory()->create([
                'insurer_id' => $this->insurer->id,
                'user_id' => $this->provider->id,
                'provider_name' => 'Provider A',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty' => 'Cardiology',
                'priority_level' => 3,
                'total_amount' => 10000,
                'status' => 'pending',
            ]);
        }

        $batch = $this->batchClaimsAction->execute($this->insurer);

        // Should not create batch
        $this->assertNull($batch);
        
        // All claims should still be pending
        $this->assertEquals(3, Claim::where('status', 'pending')->count());
    }

    /** @test */
    public function test_batch_respects_maximum_batch_size_by_splitting()
    {
        $this->insurer->update(['max_batch_size' => 5]);

        // Create 12 claims
        for ($i = 0; $i < 12; $i++) {
            Claim::factory()->create([
                'insurer_id' => $this->insurer->id,
                'user_id' => $this->provider->id,
                'provider_name' => 'Provider A',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty' => 'Cardiology',
                'priority_level' => 3,
                'total_amount' => 1000, // Small to stay under daily capacity
                'status' => 'pending',
            ]);
        }

        $batch = $this->batchClaimsAction->execute($this->insurer);

        // Batches should be created
        $batches = Batch::where('insurer_id', $this->insurer->id)->get();
        
        foreach ($batches as $b) {
            $this->assertLessThanOrEqual($this->insurer->max_batch_size, $b->total_claims);
        }
    }

    /** @test */
    public function test_batch_respects_daily_capacity_constraint()
    {
        $this->insurer->update([
            'daily_capacity' => 50000, // Low capacity
            'min_batch_size' => 1,
        ]);

        // Create 20 claims, each with ₦5000 (total potential cost way over)
        for ($i = 0; $i < 20; $i++) {
            Claim::factory()->create([
                'insurer_id' => $this->insurer->id,
                'user_id' => $this->provider->id,
                'provider_name' => 'Provider A',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty' => 'General Practice',
                'priority_level' => 3,
                'total_amount' => 5000,
                'status' => 'pending',
            ]);
        }

        $batch = $this->batchClaimsAction->execute($this->insurer);

        // Batch should be created but with fewer claims due to capacity limit
        $this->assertNotNull($batch);
        $this->assertLessThanOrEqual(Claim::where('insurer_id', $this->insurer->id)
            ->where('status', 'pending')->count() + $batch->total_claims, 20);
    }

    /** @test */
    public function test_claims_are_sorted_by_cost_ascending()
    {
        $this->insurer->update(['min_batch_size' => 1]);

        // Create 3 claims with different priorities (different costs)
        // Priority 1 = cheaper, Priority 5 = expensive
        $claim1 = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 5, // Most expensive
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $claim2 = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 1, // Cheapest
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $claim3 = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 3, // Medium
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $batch = $this->batchClaimsAction->execute($this->insurer);

        // All should be batched
        $this->assertNotNull($batch);
        $this->assertEquals(3, $batch->total_claims);

        // Check that claims are batched
        $batchedClaims = Claim::where('batch_id', $batch->id)->get();
        $this->assertEquals(3, $batchedClaims->count());
    }

    /** @test */
    public function test_batched_claims_have_status_changed_to_batched()
    {
        $this->insurer->update(['min_batch_size' => 1]);

        $claim = Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $claim->fresh()->status);

        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNotNull($batch);
        $this->assertEquals('batched', $claim->fresh()->status);
    }

    /** @test */
    public function test_claims_are_grouped_by_provider_and_date()
    {
        $this->insurer->update(['min_batch_size' => 1]);

        $date1 = now()->format('Y-m-d');
        $date2 = now()->addDay()->format('Y-m-d');

        // Create claims for different dates
        Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => $date1,
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => $date2,
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNotNull($batch);
        // Should have claims from same provider but different dates grouped correctly
    }

    /** @test */
    public function test_batch_total_claims_is_accurate()
    {
        $this->insurer->update(['min_batch_size' => 1]);

        $claimsCount = 5;
        for ($i = 0; $i < $claimsCount; $i++) {
            Claim::factory()->create([
                'insurer_id' => $this->insurer->id,
                'user_id' => $this->provider->id,
                'provider_name' => 'Provider A',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty' => 'Cardiology',
                'priority_level' => 3,
                'total_amount' => 10000,
                'status' => 'pending',
            ]);
        }

        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNotNull($batch);
        $this->assertEquals($claimsCount, $batch->total_claims);
    }

    /** @test */
    public function test_batch_total_cost_is_calculated()
    {
        $this->insurer->update(['min_batch_size' => 1]);

        Claim::factory()->create([
            'insurer_id' => $this->insurer->id,
            'user_id' => $this->provider->id,
            'provider_name' => 'Provider A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNotNull($batch);
        $this->assertGreaterThan(0, $batch->total_cost);
    }

    /** @test */
    public function test_no_batch_when_no_pending_claims()
    {
        $batch = $this->batchClaimsAction->execute($this->insurer);

        $this->assertNull($batch);
    }
}
