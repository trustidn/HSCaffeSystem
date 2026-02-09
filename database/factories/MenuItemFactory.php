<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Nasi Goreng', 'Mie Goreng', 'Americano', 'Cappuccino',
            'Latte', 'Es Teh Manis', 'Roti Bakar', 'French Fries',
            'Chicken Wings', 'Brownies', 'Cheesecake', 'Matcha Latte',
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->sentence(),
            'base_price' => fake()->randomElement([15000, 18000, 22000, 25000, 28000, 35000, 45000]),
            'image' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
