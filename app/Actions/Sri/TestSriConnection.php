<?php

namespace App\Actions\Sri;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Models\SriCredential;
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
