<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Kopi Arabica', 'Susu Segar', 'Gula Pasir', 'Es Batu', 'Tepung Terigu', 'Minyak Goreng', 'Beras', 'Telur']),
            'unit' => fake()->randomElement(['kg', 'liter', 'pcs', 'gram', 'ml']),
            'current_stock' => fake()->randomFloat(2, 1, 100),
            'minimum_stock' => fake()->randomFloat(2, 1, 10),
            'cost_per_unit' => fake()->randomElement([15000, 20000, 35000, 50000, 80000]),
            'is_active' => true,
        ];
    }
}
