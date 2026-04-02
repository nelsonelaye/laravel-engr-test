<?php

namespace App\Console\Commands;

use App\Actions\BatchClaimsAction;
use App\Models\Insurer;
use Illuminate\Console\Command;

class BatchDailyClaimsCommand extends Command
{
    protected $signature = 'claims:batch-daily {--insurer-id=}';
    protected $description = 'Batch pending claims for all insurers or a specific insurer';

    public function handle(): int
    {
        $action = app(BatchClaimsAction::class);

        if ($insurerId = $this->option('insurer-id')) {
            $insurer = Insurer::findOrFail($insurerId);
            $batch = $action->execute($insurer);
            $this->info($batch ? "Batch created: {$batch->id}" : "No batch created for insurer {$insurer->code}");
        } else {
            $insurers = Insurer::all();
            $createdCount = 0;

            foreach ($insurers as $insurer) {
                $batch = $action->execute($insurer);
                if ($batch) {
                    $this->info("Batch created for {$insurer->code}: {$batch->id}");
                    $createdCount++;
                } else {
                    $this->line("No batch for {$insurer->code}");
                }
            }

            $this->info("Total batches created: {$createdCount}");
        }

        return self::SUCCESS;
    }
}
