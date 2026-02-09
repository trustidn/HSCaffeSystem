<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuVariant>
 */
class MenuVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Hot', 'Iced']),
            'price' => fake()->randomElement([15000, 20000, 25000, 30000]),
            'sort_order' => fake()->numberBetween(0, 5),
            'is_active' => true,
        ];
    }
}
