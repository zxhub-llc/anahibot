<?php

namespace App\Actions\Extractions;

use App\Enums\ExtractionStatus;
use App\Exceptions\FiscalExtractionException;
use App\Models\Extraction;

class CancelExtraction
{
    public function handle(Extraction $extraction): Extraction
    {
        if ($extraction->status->isFinished()) {
            throw FiscalExtractionException::cannotCancel($extraction);
        }

        $extraction->update([
            'status' => ExtractionStatus::Cancelled->value,
            'completed_at' => now(),
        ]);

        return $extraction->refresh();
    }
}