<?php

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\Webhooks\DeliverWebhook;
use App\Models\Team;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class DispatchWebhook
{
    public function handle(Team $team, WebhookEvent $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::query()
            ->forTeam($team->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->isSubscribedTo($event->value));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event->value,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending->value,
            ]);

            DeliverWebhook::dispatch($delivery);
        }
    }
}