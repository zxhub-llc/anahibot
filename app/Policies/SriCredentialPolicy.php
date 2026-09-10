<?php

namespace App\Policies;

use App\Concerns\ChecksTeamPermission;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;

class SriCredentialPolicy
{
    use ChecksTeamPermission;

    public function view(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::ManageSriCredentials);
    }

    public function manage(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::ManageSriCredentials);
    }
}