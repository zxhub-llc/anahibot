<?php

namespace App\Jobs\Extractions;

use App\Actions\Extractions\CompleteExtraction;
use App\Models\ExtractionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Responsabilidad: último eslabón del pipeline. Se dispara cuando el batch de descargas de un
// ExtractionRun finaliza (con o sin fallos) y delega en CompleteExtraction el cierre del estado.
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
