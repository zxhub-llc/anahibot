<?php

namespace App\Providers;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Events\Extractions\ExtractionCompleted;
use App\Events\Extractions\ExtractionFailed;
use App\Events\Extractions\ExtractionStarted;
use App\Events\FiscalDocuments\DocumentFailed;
use App\Events\FiscalDocuments\DocumentProcessed;
use App\Events\FiscalDocuments\DocumentReceived;
use App\Listeners\DispatchDocumentWebhooks;
use App\Listeners\DispatchExtractionWebhooks;
use App\Models\Extraction;
use App\Models\FiscalDocument;
use App\Models\SriCredential;
use App\Models\WebhookEndpoint;
use App\Policies\ExtractionPolicy;
use App\Policies\FiscalDocumentPolicy;
use App\Policies\SriCredentialPolicy;
use App\Policies\WebhookEndpointPolicy;
use App\Services\FiscalExtraction\NullFiscalExtractionDriver;
use App\Services\FiscalExtraction\SriFiscalExtractionDriver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FiscalExtractionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/fiscal-extraction.php', 'fiscal-extraction');

        $this->app->bind(FiscalExtractionDriver::class, function ($app) {
            return match (config('fiscal-extraction.driver', 'null')) {
                'sri' => $app->make(SriFiscalExtractionDriver::class),
                default => $app->make(NullFiscalExtractionDriver::class),
            };
        });
    }

    public function boot(): void
    {
        Gate::policy(Extraction::class, ExtractionPolicy::class);
        Gate::policy(FiscalDocument::class, FiscalDocumentPolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);
        Gate::policy(SriCredential::class, SriCredentialPolicy::class);

        Event::subscribe(DispatchExtractionWebhooks::class);
        Event::subscribe(DispatchDocumentWebhooks::class);

        $this->publishes([
            __DIR__.'/../../config/fiscal-extraction.php' => config_path('fiscal-extraction.php'),
        ], 'fiscal-extraction-config');
    }
}