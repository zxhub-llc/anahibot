<?php

namespace Database\Factories;

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionTrigger;
use App\Models\Extraction;
use Illuminate\Database\Eloquent\Factories\Factory;

// Responsabilidad: factory de pruebas para Extraction. Asume la existencia de TeamFactory y
// UserFactory del proyecto (no se redefine el sistema de Teams).
class ExtractionFactory extends Factory
{
    protected $model = Extraction::class;

    public function definition(): array
    {
        return [
            'company_ruc' => $this->faker->numerify('#################'),
            'trigger' => ExtractionTrigger::Manual->value,
            'status' => ExtractionStatus::Pending->value,
            'attempts' => 1,
        ];
    }
}
