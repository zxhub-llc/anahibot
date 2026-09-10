<?php

namespace Database\Factories;

use App\Enums\ExtractionRunStatus;
use App\Models\ExtractionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

// Responsabilidad: factory de pruebas para ExtractionRun.
class ExtractionRunFactory extends Factory
{
    protected $model = ExtractionRun::class;

    public function definition(): array
    {
        return [
            'attempt_number' => 1,
            'status' => ExtractionRunStatus::Pending->value,
        ];
    }
}
