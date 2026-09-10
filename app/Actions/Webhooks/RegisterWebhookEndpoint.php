<?php

namespace App\Actions\Webhooks;

use App\Models\Team;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class RegisterWebhookEndpoint
{
    public function handle(Team $team, array $data): WebhookEndpoint
    {
        return WebhookEndpoint::query()->create([
            'team_id' => $team->id,
            'url' => $data['url'],
            'secret' => Str::random(40),
            'events' => $data['events'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}