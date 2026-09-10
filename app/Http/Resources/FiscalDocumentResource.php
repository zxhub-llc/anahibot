<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_ruc' => $this->company_ruc,
            'access_key' => $this->access_key,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'status' => $this->status->value,
            'is_voided' => $this->is_voided,
            'issuer_ruc' => $this->issuer_ruc,
            'issuer_name' => $this->issuer_name,
            'receiver_ruc' => $this->receiver_ruc,
            'receiver_name' => $this->receiver_name,
            'issued_at' => $this->issued_at?->toDateString(),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'has_xml' => $this->hasXml(),
            'has_pdf' => $this->hasPdf(),
            'has_json' => $this->hasJson(),
        ];
    }
}