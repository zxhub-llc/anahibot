<?php

namespace App\Events\FiscalDocuments;

use App\Models\FiscalDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentProcessed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FiscalDocument $document)
    {
    }
}