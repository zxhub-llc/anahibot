<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'ruc' => fake()->numerify('##########001'),
            'business_name' => fake()->company(),
            'trade_name' => fake()->companySuffix(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }
}