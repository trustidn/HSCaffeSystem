<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.str_pad(fake()->unique()->randomNumber(4), 4, '0', STR_PAD_LEFT),
            'table_id' => null,
            'customer_id' => null,
            'user_id' => null,
            'type' => OrderType::DineIn->value,
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'subtotal' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed->value,
            'payment_status' => PaymentStatus::Paid->value,
            'completed_at' => now(),
        ]);
    }
}
