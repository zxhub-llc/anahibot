<?php

namespace App\Concerns;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

trait ChecksTeamPermission
{
    protected function roleFor(User $user, Team $team): ?TeamRole
    {
        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        return $membership->role instanceof TeamRole
            ? $membership->role
            : TeamRole::from($membership->role);
    }

    protected function isTeamMember(User $user, Team $team): bool
    {
        return $this->roleFor($user, $team) !== null;
    }

    protected function userHasPermission(User $user, Team $team, TeamPermission $permission): bool
    {
        return $this->roleFor($user, $team)?->hasPermission($permission) ?? false;
    }
}
