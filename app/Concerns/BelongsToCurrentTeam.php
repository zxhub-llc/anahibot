<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCurrentTeam
{
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
