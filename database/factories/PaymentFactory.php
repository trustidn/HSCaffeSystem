<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => Tenant::factory(),
            'method' => PaymentMethod::Cash->value,
            'amount' => fake()->randomElement([25000, 50000, 75000, 100000]),
            'reference' => null,
            'notes' => null,
            'received_by' => null,
        ];
    }
}
