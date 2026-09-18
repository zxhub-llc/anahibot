<?php

namespace App\Actions\Sri;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Models\SriCredential;
use App\Services\FiscalExtraction\SriFiscalExtractionDriver;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestSriConnection
{
    public function __construct(private readonly FiscalExtractionDriver $driver)
    {
    }

    public function handle(SriCredential $credential): bool
    {
        try {
            $this->driver->authenticate($credential->company_ruc);

            if ($this->driver instanceof SriFiscalExtractionDriver) {
                try {
                    $taxpayer = $this->driver->fetchTaxpayer($credential->company_ruc);
                    app(SyncSriCompany::class)->handle($credential->team, $taxpayer);
                } catch (Throwable $e) {
                    Log::warning('Autenticación SRI OK, pero fallo la sincronizacion de companies', [
                        'ruc' => $credential->company_ruc,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $credential->update([
                'last_authenticated_at' => now(),
                'last_authentication_error' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            $credential->update([
                'last_authentication_error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}