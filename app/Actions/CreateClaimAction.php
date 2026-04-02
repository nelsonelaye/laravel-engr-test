<?php

namespace App\Actions;

use App\Models\Claim;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateClaimAction
{
    public function execute(array $data, User $user): Claim
    {
        return DB::transaction(function () use ($data, $user) {
            // Create the claim
            $claim = Claim::create([
                'user_id' => $user->id,
                'insurer_id' => $data['insurer_id'],
                'provider_name' => $data['provider_name'],
                'encounter_date' => $data['encounter_date'],
                'submission_date' => now(),
                'specialty' => $data['specialty'],
                'priority_level' => $data['priority_level'],
                'total_amount' => $this->calculateTotal($data['items']),
                'status' => 'pending',
            ]);

            // Create claim items
            foreach ($data['items'] as $item) {
                $claim->items()->create([
                    'name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // Optionally trigger batching job
            // dispatch(new BatchClaimsIfReadyJob($claim->insurer_id));

            return $claim;
        });
    }

    private function calculateTotal(array $items): float
    {
        return collect($items)->sum(fn($item) => $item['unit_price'] * $item['quantity']);
    }
}
