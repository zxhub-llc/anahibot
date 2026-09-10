<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Enums\TeamType;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     * Optionally creates the related Company (RUC) when the team represents a business.
     *
     * @param  array{ruc: string, business_name?: string, trade_name?: string}|null  $companyData
     */
    public function handle(
        User $user,
        string $name,
        bool $isPersonal = false,
        TeamType $type = TeamType::Company,
        ?array $companyData = null,
    ): Team {
        return DB::transaction(function () use ($user, $name, $isPersonal, $type, $companyData) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
                'type' => $type,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            if ($companyData !== null) {
                Company::create([
                    'team_id' => $team->id,
                    'ruc' => $companyData['ruc'],
                    'business_name' => $companyData['business_name'] ?? $name,
                    'trade_name' => $companyData['trade_name'] ?? null,
                ]);
            }

            $user->switchTeam($team);

            return $team;
        });
    }
}