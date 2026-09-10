<?php

namespace App\Events\Extractions;

use App\Models\Extraction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtractionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Extraction $extraction)
    {
    }
}
