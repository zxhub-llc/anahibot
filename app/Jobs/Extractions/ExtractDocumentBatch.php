<?php

namespace App\Jobs\Extractions;

use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Jobs\FiscalDocuments\DownloadFiscalDocument;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

class ExtractDocumentBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Extraction $extraction,
        public ExtractionRun $run,
        public array $references,
    ) {
    }

    public function handle(): void
    {
        $extraction = $this->extraction;
        $run = $this->run;

        $jobs = array_map(
            fn (FiscalDocumentReference $reference) => new DownloadFiscalDocument($extraction, $run, $reference),
            $this->references,
        );

        if ($jobs === []) {
            FinalizeExtraction::dispatch($run);

            return;
        }

        Bus::batch($jobs)
            ->name("extraction-run:{$run->id}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($run) {
                FinalizeExtraction::dispatch($run->refresh());
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($run) {
                $run->increment('documents_failed');
            })
            ->dispatch();
    }
}
