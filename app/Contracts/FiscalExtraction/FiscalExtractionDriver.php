<?php

namespace App\Contracts\FiscalExtraction;

use App\Data\FiscalExtraction\FiscalDocumentReference;
use Carbon\CarbonInterface;

interface FiscalExtractionDriver
{
    public function authenticate(string $companyRuc): void;
    
    public function extract(string $companyRuc, CarbonInterface $dateFrom, CarbonInterface $dateTo): array;

    public function downloadXml(string $companyRuc, string $accessKey): string;

    public function downloadRide(string $companyRuc, string $accessKey, string $direction): ?string;
}