<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Insurer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test insurer
        Insurer::create([
            'name' => 'Test Insurer',
            'code' => 'TST-001',
            'min_batch_size' => 1,
            'max_batch_size' => 100,
            'daily_capacity' => 1000000,
            'preferred_date_type' => 'encounter',
            'specialty_efficiencies' => [
                'cardiology' => 0.9,
                'orthopedics' => 1.1,
                'neurology' => 1.2,
            ],
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test Provider',
            'email' => 'provider@test.local',
        ]);
    }

    /** @test */
    public function test_get_api_insurers_returns_list()
    {
        $response = $this->getJson('/api/insurers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'name', 'specialty_efficiencies', 'preferred_date_type']
            ]
        ]);
        // Seeder creates 4 insurers, so we should have at least that many
        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    /** @test */
    public function test_post_api_claims_with_valid_data_returns_201()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                [
                    'name' => 'Consultation',
                    'unit_price' => 5000,
                    'quantity' => 1,
                ],
                [
                    'name' => 'ECG Test',
                    'unit_price' => 3000,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'user_id', 'insurer_id', 'provider_name', 'total_amount', 'status']
        ]);
        
        $this->assertDatabaseHas('claims', [
            'provider_name' => 'Dr. Smith Clinic',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
        ]);
    }

    /** @test */
    public function test_post_api_claims_with_invalid_insurer_returns_422()
    {
        $payload = [
            'insurer_id' => 9999,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    /** @test */
    public function test_post_api_claims_with_future_encounter_date_returns_422()
    {
        $insurer = Insurer::first();
        $futureDate = now()->addDays(5)->format('Y-m-d');

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => $futureDate,
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    /** @test */
    public function test_post_api_claims_with_invalid_specialty_returns_422()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'InvalidSpecialty',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    /** @test */
    public function test_post_api_claims_with_invalid_priority_returns_422()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 10, // Invalid
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_post_api_claims_with_no_items_returns_422()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    /** @test */
    public function test_post_api_claims_creates_claim_items()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
                ['name' => 'ECG Test', 'unit_price' => 3000, 'quantity' => 2],
                ['name' => 'Lab Work', 'unit_price' => 2500, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(201);
        $claimId = $response->json('data.id');

        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claimId,
            'name' => 'Consultation',
            'unit_price' => 5000,
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claimId,
            'name' => 'ECG Test',
            'unit_price' => 3000,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('claim_items', [
            'claim_id' => $claimId,
            'name' => 'Lab Work',
            'unit_price' => 2500,
            'quantity' => 1,
        ]);
    }

    /** @test */
    public function test_post_api_claims_calculates_total_amount_correctly()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Service A', 'unit_price' => 5000, 'quantity' => 2],    // 10,000
                ['name' => 'Service B', 'unit_price' => 3000, 'quantity' => 1],    // 3,000
                ['name' => 'Service C', 'unit_price' => 2500, 'quantity' => 2],    // 5,000
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(201);
        // Total should be 10,000 + 3,000 + 5,000 = 18,000
        $response->assertJson([
            'data' => ['total_amount' => 18000]
        ]);
    }

    /** @test */
    public function test_claim_status_is_pending_after_creation()
    {
        $insurer = Insurer::first();

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => ['status' => 'pending']
        ]);
    }

    /** @test */
    public function test_submission_date_is_set_to_today()
    {
        $insurer = Insurer::first();
        $today = now()->format('Y-m-d');

        $payload = [
            'insurer_id' => $insurer->id,
            'provider_name' => 'Dr. Smith Clinic',
            'encounter_date' => '2026-03-15',
            'specialty' => 'Cardiology',
            'priority_level' => 3,
            'items' => [
                ['name' => 'Consultation', 'unit_price' => 5000, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/claims', $payload);

        $response->assertStatus(201);
        $claimId = $response->json('data.id');

        // Check that submission_date is today (allow for timestamp part)
        $claim = \App\Models\Claim::find($claimId);
        $this->assertEquals($today, $claim->submission_date->format('Y-m-d'));
    }
}
