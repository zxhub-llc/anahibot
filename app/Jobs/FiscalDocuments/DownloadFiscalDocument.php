<?php
namespace App\Jobs\FiscalDocuments;

use App\Actions\FiscalDocuments\StoreFiscalDocument;
use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Enums\FiscalDocumentDirection;
use App\Jobs\Middleware\PreventDuplicateDocumentProcessing;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use App\Services\FiscalExtraction\Sri\SriAuthorizationPendingException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        if ($this->reference->direction === FiscalDocumentDirection::Received) {
            try {
                $xml = $driver->downloadXml($this->extraction->company_ruc, $this->reference->accessKey);
            } catch (SriAuthorizationPendingException $e) {
                Log::info('SRI authorization pending, deferring recheck', [
                    'extraction_id' => $this->extraction->id,
                    'access_key' => $this->reference->accessKey,
                    'error' => $e->getMessage(),
                ]);

                $document = $storeFiscalDocument->markPendingAuthorization(
                    team: $this->extraction->team,
                    run: $this->run,
                    companyRuc: $this->extraction->company_ruc,
                    reference: $this->reference,
                );

                RecheckFiscalDocumentAuthorization::dispatch($this->extraction, $document, $this->reference)
                    ->delay(now()->addMinutes(15));

                $this->run->increment('documents_processed');
                return;
            }

            $document = $storeFiscalDocument->handle(
                team: $this->extraction->team,
                run: $this->run,
                companyRuc: $this->extraction->company_ruc,
                reference: $this->reference,
                xmlContent: $xml,
            );
        } else {
            $document = $storeFiscalDocument->handleWithoutXml(
                team: $this->extraction->team,
                run: $this->run,
                companyRuc: $this->extraction->company_ruc,
                reference: $this->reference,
            );
        }

        try {
            $ride = $driver->downloadRide(
                $this->extraction->company_ruc,
                $this->reference->accessKey,
                $this->reference->direction->value,
            );

            Log::info('DownloadFiscalDocument: ride fetch result', [
                'document_id' => $document->id,
                'access_key' => $this->reference->accessKey,
                'direction' => $this->reference->direction->value,
                'ride_is_null' => $ride === null,
                'ride_bytes' => $ride !== null ? strlen($ride) : null,
            ]);

            if ($ride !== null) {
                $storeFiscalDocument->attachRide($document, $ride);

                $hasPdfMedia = $document->refresh()->hasPdf();
                Log::info('DownloadFiscalDocument: attachRide completed', [
                    'document_id' => $document->id,
                    'access_key' => $this->reference->accessKey,
                    'pdf_media_persisted' => $hasPdfMedia,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('DownloadFiscalDocument: ride download/attach failed', [
                'document_id' => $document->id,
                'access_key' => $this->reference->accessKey,
                'direction' => $this->reference->direction->value,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        if ($this->reference->direction === FiscalDocumentDirection::Received) {
            ParseFiscalDocumentJob::dispatch($document, $this->run);
        } else {
            $document->update(['status' => \App\Enums\FiscalDocumentStatus::Processed->value]);
            $this->run->increment('documents_processed');
        }
    }
}