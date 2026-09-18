<?php

namespace App\Actions\Extractions;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionStatus;
use App\Enums\ExtractionTrigger;
use App\Jobs\Extractions\ProcessExtraction;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateExtraction
{
    public function handle(User $user, Team $team, array $data): Extraction
    {
        $extraction = DB::transaction(function () use ($user, $team, $data) {
            $extraction = Extraction::query()->create([
                'team_id' => $team->id,
                'requested_by_id' => $user->id,
                'company_ruc' => $data['company_ruc'],
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'trigger' => $data['trigger'] ?? ExtractionTrigger::Manual->value,
                'status' => ExtractionStatus::Queued->value,
                'attempts' => 1,
            ]);

            ExtractionRun::query()->create([
                'extraction_id' => $extraction->id,
                'attempt_number' => 1,
                'status' => ExtractionRunStatus::Pending->value,
            ]);

            return $extraction;
        });

        ProcessExtraction::dispatch($extraction);

        return $extraction;
    }
}