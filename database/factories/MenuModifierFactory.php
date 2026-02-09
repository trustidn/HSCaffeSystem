<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuModifier>
 */
class MenuModifierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Extra Shot', 'Less Sugar', 'Extra Cheese', 'Add Egg', 'Extra Spicy', 'No Ice']),
            'price' => fake()->randomElement([0, 3000, 5000, 7000]),
            'is_active' => true,
        ];
    }
}
