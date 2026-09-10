<?php

namespace App\Services\FiscalExtraction;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Exceptions\FiscalExtractionException;

class NullFiscalExtractionDriver implements FiscalExtractionDriver
{
    public function authenticate(string $companyRuc): void
    {
        // Sin origen externo configurado: no hay sesión que establecer.
    }

    public function extract(string $companyRuc): array
    {
        throw FiscalExtractionException::driverNotConfigured();
    }

    public function downloadXml(string $companyRuc, string $accessKey): string
    {
        throw FiscalExtractionException::driverNotConfigured();
    }

    public function downloadRide(string $companyRuc, string $accessKey): ?string
    {
        throw FiscalExtractionException::driverNotConfigured();
    }
}
