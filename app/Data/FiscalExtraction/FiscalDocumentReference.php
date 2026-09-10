<?php

namespace App\Data\FiscalExtraction;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;

final readonly class FiscalDocumentReference
{
    public function __construct(
        public string $accessKey,
        public FiscalDocumentType $type,
        public FiscalDocumentDirection $direction,
        public ?string $issuerRuc = null,
        public ?string $receiverRuc = null,
        public bool $isVoided = false,
    ) {
    }
}
