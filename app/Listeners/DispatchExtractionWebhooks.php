<?php

namespace App\Listeners;

use App\Actions\Webhooks\DispatchWebhook;
use App\Enums\WebhookEvent;
use App\Events\Extractions\ExtractionCompleted;
use App\Events\Extractions\ExtractionFailed;
use App\Events\Extractions\ExtractionStarted;
use App\Http\Resources\ExtractionResource;

class DispatchExtractionWebhooks
{
    public function __construct(private readonly DispatchWebhook $dispatchWebhook)
    {
    }

    public function handleStarted(ExtractionStarted $event): void
    {
        $this->dispatch(WebhookEvent::ExtractionStarted, $event->extraction);
    }

    public function handleCompleted(ExtractionCompleted $event): void
    {
        $this->dispatch(WebhookEvent::ExtractionCompleted, $event->extraction);
    }

    public function handleFailed(ExtractionFailed $event): void
    {
        $this->dispatch(WebhookEvent::ExtractionFailed, $event->extraction);
    }

    private function dispatch(WebhookEvent $event, $extraction): void
    {
        $this->dispatchWebhook->handle(
            $extraction->team,
            $event,
            (new ExtractionResource($extraction))->resolve(),
        );
    }

    public function subscribe($events): array
    {
        return [
            ExtractionStarted::class => 'handleStarted',
            ExtractionCompleted::class => 'handleCompleted',
            ExtractionFailed::class => 'handleFailed',
        ];
    }
}
