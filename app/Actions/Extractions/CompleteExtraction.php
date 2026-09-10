<?php

namespace App\Actions\Extractions;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionStatus;
use App\Events\Extractions\ExtractionCompleted;
use App\Events\Extractions\ExtractionFailed;
use App\Models\Extraction;
use App\Models\ExtractionRun;

class CompleteExtraction
{
    public function handle(ExtractionRun $run): Extraction
    {
        $extraction = $run->extraction;

        $run->update([
            'status' => $run->documents_failed > 0 && $run->documents_processed === 0
                ? ExtractionRunStatus::Failed->value
                : ExtractionRunStatus::Completed->value,
            'finished_at' => now(),
        ]);

        $status = match (true) {
            $run->documents_processed === 0 && $run->documents_failed > 0 => ExtractionStatus::Failed,
            $run->documents_failed > 0 => ExtractionStatus::CompletedWithErrors,
            default => ExtractionStatus::Completed,
        };

        $extraction->update([
            'status' => $status->value,
            'completed_at' => now(),
            'last_error' => $status === ExtractionStatus::Failed ? $run->error_message : null,
        ]);

        $extraction = $extraction->refresh();

        if ($status === ExtractionStatus::Failed) {
            event(new ExtractionFailed($extraction));
        } else {
            event(new ExtractionCompleted($extraction));
        }

        return $extraction;
    }
}