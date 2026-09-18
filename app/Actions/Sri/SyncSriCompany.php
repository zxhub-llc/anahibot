<?php

namespace App\Actions\Sri;

use App\Models\Company;
use App\Models\Team;

class SyncSriCompany
{
    public function handle(
        Team $team,
        array $taxpayer
    ): Company {
        return $team->company()->updateOrCreate(
            ['team_id' => $team->id],
            [
                'ruc' => $taxpayer['ruc'],
                'business_name' => $taxpayer['business_name'] ?? null,
                'trade_name' => $taxpayer['trade_name'] ?? null,
                'email' => $taxpayer['email'] ?? null,
                'phone' => $taxpayer['phone'] ?? null,
                'address' => $taxpayer['address'] ?? null,
                'legal_representative' => $taxpayer['legal_representative'] ?? null,
                'legal_representative_id' => $taxpayer['legal_representative_id'] ?? null,
            ],
        );
    }
}