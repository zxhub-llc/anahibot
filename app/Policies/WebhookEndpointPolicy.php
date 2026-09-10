<?php

namespace App\Policies;

use App\Concerns\ChecksTeamPermission;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    use ChecksTeamPermission;

    public function viewAny(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->isTeamMember($user, $team);
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->isTeamMember($user, $endpoint->team);
    }

    public function create(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::ManageWebhooks);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->userHasPermission($user, $endpoint->team, TeamPermission::ManageWebhooks);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->userHasPermission($user, $endpoint->team, TeamPermission::ManageWebhooks);
    }
}
