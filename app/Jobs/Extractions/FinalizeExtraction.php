<?php

namespace App\Jobs\Extractions;

use App\Actions\Extractions\CompleteExtraction;
use App\Models\ExtractionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeExtraction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ExtractionRun $run)
    {
    }

    public function handle(CompleteExtraction $completeExtraction): void
    {
        $completeExtraction->handle($this->run);
    }
}
