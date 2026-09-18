<?php

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;

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
