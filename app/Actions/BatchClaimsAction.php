<?php

namespace App\Actions;

use App\Models\Batch;
use App\Models\Claim;
use App\Models\Insurer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BatchClaimsAction
{
    /**
     * Execute batching for a specific insurer
     */
    public function execute(Insurer $insurer, ?Carbon $batchDate = null): ?Batch
    {
        $batchDate = $batchDate ?? now();

        try {
            // Fetch all unbatched, pending claims for this insurer
            $unbatchedClaims = $insurer->claims()
                ->where('status', 'pending')
                ->get();

            if ($unbatchedClaims->isEmpty()) {
                return null;
            }

            // Calculate processing cost for each claim
            $claims = $unbatchedClaims->map(function (Claim $claim) use ($insurer, $batchDate) {
                $claim->processing_cost = $this->calculateProcessingCost($claim, $insurer, $batchDate);
                return $claim;
            });

            // Sort by processing cost (ascending) - greedy approach: cheapest first
            $sortedClaims = $claims->sortBy('processing_cost')->values();

            // Group claims by provider and date to create batches
            $batchesByProvider = $this->groupClaimsIntoBatches($sortedClaims, $insurer);

            $createdBatches = [];

            foreach ($batchesByProvider as $provider => $claimsByDate) {
                foreach ($claimsByDate as $date => $claimsForBatch) {
                    // Convert array to Collection if needed
                    if (is_array($claimsForBatch)) {
                        $claimsForBatch = collect($claimsForBatch);
                    }
                    
                    $batch = $this->createBatchIfValid(
                        $insurer,
                        $provider,
                        $date,
                        $claimsForBatch
                    );

                    if ($batch) {
                        $createdBatches[] = $batch;
                    }
                }
            }

            return count($createdBatches) > 0 ? $createdBatches[0] : null;
        } catch (\Exception $e) {
            \Log::error('Batching error for insurer ' . $insurer->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**  Calculate processing cost for a claim with various constraints multipliers **/
    private function calculateProcessingCost(Claim $claim, Insurer $insurer, Carbon $date): float
    {
        $baseAmount = 10000; // base processing cost: 100 Naira (10,000 kobo)

        // Day of month multiplier
        $dayOfMonth = $date->day;
        $dayFactor = 0.20 + ($dayOfMonth / 30) * 0.30; // first day + other days of the month

        // sSpecialty multiplier
        $specialtyEfficiencies = $insurer->specialty_efficiencies ?? [];
        $specialtyKey = strtolower(str_replace(' ', '_', $claim->specialty));
        $specialtyMultiplier = $specialtyEfficiencies[$specialtyKey] ?? 1.0;

        // Priority multiplier (scaled to 1.0 - 1.2)
        $priorityMultiplier = 1.0 + ($claim->priority_level / 5) * 0.2; // with a 20% premium

   
        $claimValue = (float)$claim->total_amount; // added to processing cost

        // final cost: base processing cost + claim value
        $finalCost = ($baseAmount * $dayFactor * $specialtyMultiplier * $priorityMultiplier) + $claimValue;

        return (float)$finalCost;
    }

    private function groupClaimsIntoBatches(Collection $claims, Insurer $insurer): array
    {
        $batchesByProvider = [];

        foreach ($claims as $claim) {
            // Get the date to use for batching based on insurer preference
            $batchDate = $insurer->preferred_date_type === 'submission'
                ? $claim->submission_date
                : $claim->encounter_date;

            $provider = $claim->provider_name;
            $dateStr = $batchDate->format('Y-m-d');

            if (!isset($batchesByProvider[$provider])) {
                $batchesByProvider[$provider] = [];
            }

            if (!isset($batchesByProvider[$provider][$dateStr])) {
                $batchesByProvider[$provider][$dateStr] = [];
            }

            $batchesByProvider[$provider][$dateStr][] = $claim;
        }

        return $batchesByProvider;
    }

    /**
     * Create batch if it meets all constraints, otherwise handle overages
     */
    private function createBatchIfValid(Insurer $insurer, string $provider, string $dateStr, Collection $claims): ?Batch
    {
        $batchDate = Carbon::createFromFormat('Y-m-d', $dateStr);
        $claimIds = $claims->pluck('id')->toArray();
        $totalCost = $claims->sum('processing_cost');
        $claimCount = count($claimIds);

        // Check: Minimum batch size
        if ($claimCount < $insurer->min_batch_size) {
            \Log::info("Batch for {$provider} on {$dateStr} has {$claimCount} claims, below min {$insurer->min_batch_size}");
            return null;
        }

        // Check: Maximum batch size - split if exceeded
        if ($claimCount > $insurer->max_batch_size) {
            \Log::info("Batch for {$provider} on {$dateStr} has {$claimCount} claims, exceeds max {$insurer->max_batch_size}. Splitting...");
            $splits = array_chunk($claimIds, $insurer->max_batch_size);
            $lastBatch = null;
            foreach ($splits as $splitIds) {
                $lastBatch = $this->createBatchRecord($insurer, $provider, $batchDate, $splitIds);
            }
            return $lastBatch;
        }

        // Check: Daily capacity
        $dailyUsage = Batch::where('insurer_id', $insurer->id)
            ->where('batch_date', $batchDate)
            ->sum('total_cost');

        if ($dailyUsage + $totalCost > $insurer->daily_capacity) {
            \Log::info("Batch for {$provider} on {$dateStr} would exceed daily capacity (₦{$dailyUsage} + ₦{$totalCost} > ₦{$insurer->daily_capacity})");
            return null;
        }

        // All constraints met - create batch
        return $this->createBatchRecord($insurer, $provider, $batchDate, $claimIds);
    }

    /**
     * Create batch record in database and update claims
     */
    private function createBatchRecord(Insurer $insurer, string $provider, Carbon $batchDate, array $claimIds): ?Batch
    {
        // Calculate total cost from claims
        $totalCost = Claim::whereIn('id', $claimIds)->sum('processing_cost');

        $batch = Batch::create([
            'insurer_id' => $insurer->id,
            'provider_name' => $provider,
            'batch_date' => $batchDate,
            'status' => 'pending',
            'total_claims' => count($claimIds),
            'total_cost' => $totalCost,
        ]);

        // Update claims to be batched
        Claim::whereIn('id', $claimIds)
            ->update([
                'batch_id' => $batch->id,
                'status' => 'batched',
            ]);

        // Send notification to the first provider in the batch
        $firstClaim = Claim::find($claimIds[0]);
        if ($firstClaim && $firstClaim->user) {
            $firstClaim->user->notify(new \App\Notifications\BatchCreatedNotification($batch));
        }

        return $batch;
    }
}
