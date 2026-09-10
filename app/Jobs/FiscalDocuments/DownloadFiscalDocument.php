<?php

namespace App\Jobs\FiscalDocuments;

use App\Actions\FiscalDocuments\StoreFiscalDocument;
use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Jobs\Middleware\PreventDuplicateDocumentProcessing;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Responsabilidad: descarga el XML (y el RIDE si está disponible) de un único documento fiscal
// referenciado por el Driver, lo persiste vía StoreFiscalDocument (idempotente) y despacha el
// parseo. Granularidad por documento individual para permitir retries y concurrencia finos.
class DownloadFiscalDocument implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public Extraction $extraction,
        public ExtractionRun $run,
        public FiscalDocumentReference $reference,
    ) {
    }

    public function middleware(): array
    {
        return [new PreventDuplicateDocumentProcessing($this->extraction->team_id, $this->reference->accessKey)];
    }

    public function handle(FiscalExtractionDriver $driver, StoreFiscalDocument $storeFiscalDocument): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $xml = $driver->downloadXml($this->extraction->company_ruc, $this->reference->accessKey);

        $document = $storeFiscalDocument->handle(
            team: $this->extraction->team,
            run: $this->run,
            companyRuc: $this->extraction->company_ruc,
            reference: $this->reference,
            xmlContent: $xml,
        );

        $ride = $driver->downloadRide($this->extraction->company_ruc, $this->reference->accessKey);

        if ($ride !== null) {
            $storeFiscalDocument->attachRide($document, $ride);
        }

        ParseFiscalDocumentJob::dispatch($document, $this->run);
    }
}
