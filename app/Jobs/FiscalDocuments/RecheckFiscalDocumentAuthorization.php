<?php
namespace App\Jobs\FiscalDocuments;

use App\Actions\FiscalDocuments\StoreFiscalDocument;
use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Models\Extraction;
use App\Models\FiscalDocument;
use App\Services\FiscalExtraction\Sri\SriAuthorizationPendingException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecheckFiscalDocumentAuthorization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public array $backoff = [900, 1800, 3600, 7200, 14400];
    public int $tries = 5;

    public function __construct(
        public Extraction $extraction,
        public FiscalDocument $document,
        public FiscalDocumentReference $reference,
    ) {
    }

    public function handle(FiscalExtractionDriver $driver, StoreFiscalDocument $storeFiscalDocument): void
    {
        try {
            $xml = $driver->downloadXml($this->extraction->company_ruc, $this->reference->accessKey);
        } catch (SriAuthorizationPendingException $e) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 14400);
            return;
        }

        $storeFiscalDocument->completeFromPending($this->document, $xml);
        ParseFiscalDocumentJob::dispatch($this->document->refresh(), $this->extraction->runs()->latest('attempt_number')->firstOrFail());
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('SRI authorization recheck exhausted, needs manual review', [
            'document_id' => $this->document->id,
            'access_key' => $this->reference->accessKey,
        ]);
    }
}