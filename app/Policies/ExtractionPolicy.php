<?php

namespace App\Policies;

use App\Concerns\ChecksTeamPermission;
use App\Enums\TeamPermission;
use App\Models\Extraction;
use App\Models\Team;
use App\Models\User;

class ExtractionPolicy
{
    use ChecksTeamPermission;

    public function viewAny(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::ViewExtractions);
    }

    public function view(User $user, Extraction $extraction): bool
    {
        return $this->userHasPermission($user, $extraction->team, TeamPermission::ViewExtractions);
    }

    public function create(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::CreateExtraction);
    }

    public function update(User $user, Extraction $extraction): bool
    {
        return $this->userHasPermission($user, $extraction->team, TeamPermission::ManageExtractions);
    }

    public function delete(User $user, Extraction $extraction): bool
    {
        return $this->userHasPermission($user, $extraction->team, TeamPermission::ManageExtractions);
    }

    public function retry(User $user, Extraction $extraction): bool
    {
        return $this->update($user, $extraction);
    }

    public function cancel(User $user, Extraction $extraction): bool
    {
        return $this->update($user, $extraction);
    }
}
