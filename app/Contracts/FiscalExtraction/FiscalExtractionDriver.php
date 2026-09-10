<?php

namespace App\Contracts\FiscalExtraction;

use App\Data\FiscalExtraction\FiscalDocumentReference;

interface FiscalExtractionDriver
{
    /**
     * Authenticates against the tax origin for the RUC of the indicated company.
     */
    public function authenticate(string $companyRuc): void;

    /**
     * List the references to available documents (received and issued) for the period
     * and RUC indicated. It does not download content, only references identifiable by access key.
     *
     * @return FiscalDocumentReference[]
     */
    public function extract(string $companyRuc): array;

    /**
     * Download the raw XML content of a document identified by its access key.
     */
    public function downloadXml(string $companyRuc, string $accessKey): string;

    /**
     * Download the binary content of the PDF/RIDE when it is available from the source.
     * Returns null when the source does not provide a PDF representation for that document.
     */
    public function downloadRide(string $companyRuc, string $accessKey): ?string;
}
