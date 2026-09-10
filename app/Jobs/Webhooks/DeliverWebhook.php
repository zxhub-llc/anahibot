<?php

namespace App\Jobs\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public array $backoff = [5, 30, 120, 600, 1800];

    public function __construct(public WebhookDelivery $delivery)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->delivery->id))->releaseAfter(30)];
    }

    public function handle(): void
    {
        $endpoint = $this->delivery->webhookEndpoint;
        $body = json_encode($this->delivery->payload);
        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        $this->delivery->increment('attempts');

        $response = Http::withHeaders([
            'X-Signature' => $signature,
            'X-Event' => $this->delivery->event,
        ])
            ->timeout(10)
            ->post($endpoint->url, $this->delivery->payload);

        $this->delivery->update([
            'status' => $response->successful()
                ? WebhookDeliveryStatus::Delivered->value
                : WebhookDeliveryStatus::Failed->value,
            'response_status' => $response->status(),
            'response_body' => substr($response->body(), 0, 2000),
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook endpoint respondió {$response->status()}.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->delivery->update(['status' => WebhookDeliveryStatus::Failed->value]);
    }
}
