<?php

namespace App\Jobs\FiscalDocuments;

use App\Actions\FiscalDocuments\ParseFiscalDocument;
use App\Enums\FiscalDocumentStatus;
use App\Models\ExtractionRun;
use App\Models\FiscalDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Responsabilidad: parsea y normaliza un FiscalDocument ya descargado, y actualiza los contadores
// agregados del ExtractionRun (documents_processed / documents_failed) usado por FinalizeExtraction.
class ParseFiscalDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FiscalDocument $document,
        public ExtractionRun $run,
    ) {
    }

    public function handle(ParseFiscalDocument $parseFiscalDocument): void
    {
        $document = $parseFiscalDocument->handle($this->document);

        if ($document->status === FiscalDocumentStatus::Processed) {
            $this->run->increment('documents_processed');
        } else {
            $this->run->increment('documents_failed');
        }
    }
}
