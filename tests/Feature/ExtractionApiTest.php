<?php

namespace Tests\Feature;

use App\Enums\ExtractionStatus;
use App\Jobs\Extractions\ProcessExtraction;
use App\Models\Extraction;
use App\Models\Team;
use App\Models\User;
use App\Enums\TeamRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// Responsabilidad: test de feature del flujo Create -> Queue para Extraction vía API.
// Asume TeamFactory/UserFactory y relación currentTeam del proyecto base (no se redefinen aquí).
class ExtractionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_extraction_and_queues_processing(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => TeamRole::Owner->value]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/teams/{$team->slug}/extractions", [
                'company_ruc' => '1790012345001',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('extractions', [
            'team_id' => $team->id,
            'company_ruc' => '1790012345001',
            'status' => ExtractionStatus::Queued->value,
        ]);

        Queue::assertPushed(ProcessExtraction::class);
    }

    public function test_it_cannot_retry_an_extraction_still_running(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => TeamRole::Owner->value]);
        $user->update(['current_team_id' => $team->id]);

        $extraction = Extraction::factory()->for($team)->create([
            'status' => ExtractionStatus::Running->value,
        ]);

        $response = $this->actingAs($user)
            ->post("/{$team->slug}/extractions/{$extraction->id}/retry");

        $response->assertSessionHasErrors();
    }
}
