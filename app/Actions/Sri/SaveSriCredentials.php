<?php

namespace App\Actions\Sri;

use App\Models\SriCredential;
use App\Models\Team;

class SaveSriCredentials
{
    public function handle(Team $team, array $data): SriCredential
    {
        $credential = SriCredential::query()->firstOrNew(['company_ruc' => $data['company_ruc']]);

        $credential->fill([
            'team_id' => $team->id,
            'company_ruc' => $data['company_ruc'],
            'username' => $data['username'] ?? '',
            'environment' => $data['environment'] ?? $credential->environment ?? 'production',
        ]);

        if (! empty($data['password'])) {
            $credential->password = $data['password'];
        }

        $credential->save();

        return $credential;
    }
}
