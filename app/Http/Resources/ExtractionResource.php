<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_ruc' => $this->company_ruc,
            'trigger' => $this->trigger->value,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
