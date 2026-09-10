<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateFiscalDocumentException extends RuntimeException
{
    public static function forAccessKey(string $accessKey): self
    {
        return new self("The tax document with access code [{$accessKey}] has already been processed.");
    }
}
