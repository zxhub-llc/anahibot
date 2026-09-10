<?php

namespace App\Policies;

use App\Concerns\ChecksTeamPermission;
use App\Enums\TeamPermission;
use App\Models\FiscalDocument;
use App\Models\Team;
use App\Models\User;

class FiscalDocumentPolicy
{
    use ChecksTeamPermission;

    public function viewAny(User $user, ?Team $team = null): bool
    {
        $team ??= $user->currentTeam;

        return $team !== null && $this->userHasPermission($user, $team, TeamPermission::ViewFiscalDocuments);
    }

    public function view(User $user, FiscalDocument $document): bool
    {
        return $this->userHasPermission($user, $document->team, TeamPermission::ViewFiscalDocuments);
    }
}