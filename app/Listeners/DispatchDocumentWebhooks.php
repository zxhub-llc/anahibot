<?php

namespace App\Listeners;

use App\Actions\Webhooks\DispatchWebhook;
use App\Enums\WebhookEvent;
use App\Events\FiscalDocuments\DocumentFailed;
use App\Events\FiscalDocuments\DocumentProcessed;
use App\Events\FiscalDocuments\DocumentReceived;
use App\Http\Resources\FiscalDocumentResource;

class DispatchDocumentWebhooks
{
    public function __construct(private readonly DispatchWebhook $dispatchWebhook)
    {
    }

    public function handleReceived(DocumentReceived $event): void
    {
        $this->dispatch(WebhookEvent::DocumentReceived, $event->document);
    }

    public function handleProcessed(DocumentProcessed $event): void
    {
        $this->dispatch(WebhookEvent::DocumentProcessed, $event->document);
    }

    public function handleFailed(DocumentFailed $event): void
    {
        $this->dispatch(WebhookEvent::DocumentFailed, $event->document);
    }

    private function dispatch(WebhookEvent $event, $document): void
    {
        $this->dispatchWebhook->handle(
            $document->team,
            $event,
            (new FiscalDocumentResource($document))->resolve(),
        );
    }

    public function subscribe($events): array
    {
        return [
            DocumentReceived::class => 'handleReceived',
            DocumentProcessed::class => 'handleProcessed',
            DocumentFailed::class => 'handleFailed',
        ];
    }
}