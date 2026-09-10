<?php

namespace App\Exceptions;

use App\Models\Extraction;
use App\Models\FiscalDocument;
use RuntimeException;

class FiscalExtractionException extends RuntimeException
{
    public static function cannotRetry(Extraction $extraction): self
    {
        return new self("The extraction #{$extraction->id} is not in a state that allows retry ({$extraction->status->value}).");
    }

    public static function cannotCancel(Extraction $extraction): self
    {
        return new self("The extraction #{$extraction->id} has already finished and cannot be cancelled.");
    }

    public static function missingXml(FiscalDocument $document): self
    {
        return new self("The stored XML for the tax document #{$document->id} was not found.");
    }

    public static function driverNotConfigured(): self
    {
        return new self('There is no FiscalExtractionDriver linked in the services container.');
    }
}
