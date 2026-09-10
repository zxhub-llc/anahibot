<?php

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;

// Responsabilidad: middleware de job que aplica un lock selectivo (WithoutOverlapping a nivel de
// documento, no de Team completo) para evitar que dos workers procesen el mismo access_key en
// paralelo durante una ventana de descarga. Complementa, no reemplaza, el unique constraint de BD.
class PreventDuplicateDocumentProcessing
{
    public function __construct(
        private readonly int $teamId,
        private readonly string $accessKey,
    ) {
    }

    public function handle(object $job, \Closure $next): void
    {
        $lock = Cache::lock("fiscal-document:{$this->teamId}:{$this->accessKey}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $next($job);
        } finally {
            $lock->release();
        }
    }
}
