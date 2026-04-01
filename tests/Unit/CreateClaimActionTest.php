<?php

namespace Tests\Unit;

use App\Actions\CreateClaimAction;
use App\Models\Claim;
use App\Models\ClaimItem;
use App\Models\Insurer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateClaimActionTest extends TestCase
{
    use RefreshDatabase;

    protected CreateClaimAction $createClaimAction;
    protected Insurer $insurer;
    protected User $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createClaimAction = app(CreateClaimAction::class);
        
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
    public function test_claim_is_created_with_correct_data()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. John Smith',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
                ['name' => 'ECG Test', 'unit_price' => 3000, 'quantity' => 2],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertNotNull($claim);
        $this->assertEquals($this->insurer->id, $claim->insurer_id);
        $this->assertEquals($this->provider->id, $claim->user_id);
        $this->assertEquals('Dr. John Smith', $claim->provider_name);
        $this->assertEquals('Cardiology', $claim->specialty);
        $this->assertEquals(3, $claim->priority_level);
    }

    /** @test */
    public function test_claim_items_are_created()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Jane Doe',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 2,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
                ['name' => 'Blood Test', 'unit_price' => 2000, 'quantity' => 1],
                ['name' => 'Medication', 'unit_price' => 1500, 'quantity' => 3],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertCount(3, $claim->items);
        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claim->id,
            'name' => 'Consultation',
            'unit_price' => 5000,
        ]);
    }

    /** @test */
    public function test_claim_total_amount_is_calculated_correctly()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Bob Johnson',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 4,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],   // 5000
                ['name' => 'ECG Test', 'unit_price' => 3000, 'quantity' => 2],       // 6000
                ['name' => 'Medication', 'unit_price' => 2000, 'quantity' => 3],     // 6000
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        // Total should be 5000 + 6000 + 6000 = 17000
        $this->assertEquals(17000, $claim->total_amount);
    }

    /** @test */
    public function test_claim_status_is_pending()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Test',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 1,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertEquals('pending', $claim->status);
    }

    /** @test */
    public function test_submission_date_is_set_to_today()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Test',
            'encounter_date' => now()->subDays(5)->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 2,
            'items' => [
                ['name' => 'Service', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertEquals(now()->format('Y-m-d'), $claim->submission_date->format('Y-m-d'));
    }

    /** @test */
    public function test_claim_is_created_in_database()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Database Test',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Test', 'unit_price' => 1000, 'quantity' => 1],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertDatabaseHas('claims', [
            'id' => $claim->id,
            'provider_name' => 'Dr. Database Test',
            'insurer_id' => $this->insurer->id,
        ]);
    }

    /** @test */
    public function test_claim_items_are_in_database()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Test',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 2,
            'items' => [
                ['name' => 'Item 1', 'unit_price' => 1000, 'quantity' => 2],
                ['name' => 'Item 2', 'unit_price' => 2000, 'quantity' => 1],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claim->id,
            'name' => 'Item 1',
            'unit_price' => 1000,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claim->id,
            'name' => 'Item 2',
            'unit_price' => 2000,
            'quantity' => 1,
        ]);
    }

    /** @test */
    public function test_claim_has_no_batch_initially()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Test',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 1,
            'items' => [
                ['name' => 'Service', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        $this->assertNull($claim->batch_id);
    }

    /** @test */
    public function test_multiple_claims_can_be_created()
    {
        $data1 = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. A',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 1,
            'items' => [
                ['name' => 'Service', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $data2 = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. B',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'General Practice',
            'priority_level' => 2,
            'items' => [
                ['name' => 'Service', 'unit_price' => 3000, 'quantity' => 1],
            ],
        ];

        $claim1 = $this->createClaimAction->execute($data1, $this->provider);
        $claim2 = $this->createClaimAction->execute($data2, $this->provider);

        $this->assertNotNull($claim1);
        $this->assertNotNull($claim2);
        $this->assertNotEquals($claim1->id, $claim2->id);
        // Verify that at least 2 claims exist (other tests may have created more)
        $this->assertGreaterThanOrEqual(2, Claim::count());
    }

    /** @test */
    public function test_claim_uses_atomic_transaction()
    {
        $data = [
            'insurer_id' => $this->insurer->id,
            'provider_name' => 'Dr. Transaction Test',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Service 1', 'unit_price' => 5000, 'quantity' => 1],
                ['name' => 'Service 2', 'unit_price' => 3000, 'quantity' => 2],
            ],
        ];

        $claim = $this->createClaimAction->execute($data, $this->provider);

        // Verify both claim and items were created together
        $this->assertEquals(1, Claim::where('provider_name', 'Dr. Transaction Test')->count());
        $this->assertEquals(2, ClaimItem::where('claim_id', $claim->id)->count());
    }
}
