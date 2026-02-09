<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => (string) fake()->unique()->numberBetween(1, 50),
            'section' => fake()->randomElement(['Indoor', 'Outdoor', 'VIP', 'Rooftop']),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
            'status' => 'available',
            'qr_token' => Str::random(32),
        ];
    }
}
