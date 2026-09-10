<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SriCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_ruc' => $this->company_ruc,
            'environment' => $this->environment,
            'last_authenticated_at' => $this->last_authenticated_at?->toIso8601String(),
            'last_authentication_error' => $this->last_authentication_error,
            'is_connected' => $this->last_authenticated_at !== null && $this->last_authentication_error === null,
        ];
    }
}