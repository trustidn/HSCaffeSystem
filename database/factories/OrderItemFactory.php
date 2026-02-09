<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomElement([15000, 20000, 25000, 30000, 45000]);
        $qty = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_variant_id' => null,
            'item_name' => fake()->word(),
            'variant_name' => null,
            'unit_price' => $price,
            'quantity' => $qty,
            'subtotal' => $price * $qty,
            'notes' => null,
        ];
    }
}
