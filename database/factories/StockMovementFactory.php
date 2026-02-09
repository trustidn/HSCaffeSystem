<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ingredient_id' => Ingredient::factory(),
            'type' => StockMovementType::In->value,
            'quantity' => fake()->randomFloat(2, 1, 50),
            'cost_per_unit' => fake()->randomElement([15000, 25000, 50000]),
            'reference' => null,
            'notes' => null,
            'user_id' => null,
        ];
    }
}
