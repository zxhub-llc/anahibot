<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestRun = $this->relationLoaded('runs')
            ? $this->runs->sortByDesc('attempt_number')->first()
            : $this->runs()->latest('attempt_number')->first();

        $found = $latestRun?->documents_found ?? 0;
        $processed = $latestRun?->documents_processed ?? 0;
        $failed = $latestRun?->documents_failed ?? 0;
        $progress = $found > 0 ? (int) round((($processed + $failed) / $found) * 100) : 0;

        return [
            'id' => $this->id,
            'company_ruc' => $this->company_ruc,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'trigger' => $this->trigger->value,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at->toIso8601String(),
            'documents_found' => $found,
            'documents_processed' => $processed,
            'documents_failed' => $failed,
            'progress' => $progress,
        ];
    }
}