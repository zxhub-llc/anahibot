<?php

namespace Tests\Feature;

use App\Actions\FiscalDocuments\StoreFiscalDocument;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Models\Extraction;
use App\Models\ExtractionRun;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Responsabilidad: verifica la regla de idempotencia obligatoria — la misma clave de acceso
// dentro del mismo Team nunca produce un segundo FiscalDocument ni un segundo archivo en S3.
class FiscalDocumentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_the_same_access_key_twice_does_not_duplicate(): void
    {
        Storage::fake('s3');

        $team = Team::factory()->create();
        $extraction = Extraction::factory()->for($team)->create();
        $run = ExtractionRun::factory()->for($extraction)->create(['attempt_number' => 1]);

        $reference = new FiscalDocumentReference(
            accessKey: '1234567890123456789012345678901234567890123456',
            type: FiscalDocumentType::Invoice,
            direction: FiscalDocumentDirection::Received,
        );

        $action = app(StoreFiscalDocument::class);

        $first = $action->handle($team, $run, '1790012345001', $reference, '<xml></xml>');
        $second = $action->handle($team, $run, '1790012345001', $reference, '<xml></xml>');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $team->fiscalDocuments()->count());
    }
}
