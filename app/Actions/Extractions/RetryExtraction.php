<?php

namespace App\Actions\Extractions;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionStatus;
use App\Enums\ExtractionTrigger;
use App\Exceptions\FiscalExtractionException;
use App\Jobs\Extractions\ProcessExtraction;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;

class RetryExtraction
{
    public function handle(Extraction $extraction): ExtractionRun
    {
        if (! in_array($extraction->status, [ExtractionStatus::Failed, ExtractionStatus::CompletedWithErrors], true)) {
            throw FiscalExtractionException::cannotRetry($extraction);
        }

        $run = DB::transaction(function () use ($extraction) {
            $nextAttempt = $extraction->attempts + 1;

            $extraction->update([
                'trigger' => ExtractionTrigger::Retry->value,
                'status' => ExtractionStatus::Queued->value,
                'attempts' => $nextAttempt,
                'last_error' => null,
            ]);

            return ExtractionRun::query()->create([
                'extraction_id' => $extraction->id,
                'attempt_number' => $nextAttempt,
                'status' => ExtractionRunStatus::Pending->value,
            ]);
        });

        ProcessExtraction::dispatch($extraction);

        return $run;
    }
}