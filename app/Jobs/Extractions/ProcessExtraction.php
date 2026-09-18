<?php

namespace App\Jobs\Extractions;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionStatus;
use App\Events\Extractions\ExtractionFailed;
use App\Events\Extractions\ExtractionStarted;
use App\Models\Extraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessExtraction implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    
    public int $tries = 1;

    public function __construct(public Extraction $extraction)
    {
    }

    public function uniqueId(): string
    {
        return "extraction:{$this->extraction->id}";
    }

    public function handle(FiscalExtractionDriver $driver): void
    {
        $extraction = $this->extraction->refresh();

        if ($extraction->status === ExtractionStatus::Cancelled) {
            return;
        }

        $run = $extraction->runs()->latest('attempt_number')->firstOrFail();

        $run->update([
            'status' => ExtractionRunStatus::Running->value,
            'started_at' => now(),
        ]);

        $extraction->update(['status' => ExtractionStatus::Running->value]);

        event(new ExtractionStarted($extraction));

        try {
            $driver->authenticate($extraction->company_ruc);
            $references = $driver->extract($extraction->company_ruc, $extraction->date_from, $extraction->date_to);

            $run->update(['documents_found' => count($references)]);

            ExtractDocumentBatch::dispatch($extraction, $run, $references);
        } catch (Throwable $exception) {
            $run->update([
                'status' => ExtractionRunStatus::Failed->value,
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $extraction->update([
                'status' => ExtractionStatus::Failed->value,
                'last_error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            event(new ExtractionFailed($extraction->refresh()));
        }
    }
}
