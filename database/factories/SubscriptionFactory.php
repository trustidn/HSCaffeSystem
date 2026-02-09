<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $plan = SubscriptionPlan::factory();
        $startsAt = now()->subDays($this->faker->numberBetween(0, 30));

        return [
            'tenant_id' => Tenant::factory(),
            'subscription_plan_id' => $plan,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonths(1),
            'price_paid' => 199000,
            'status' => SubscriptionStatus::Active,
            'payment_reference' => 'TRX-'.$this->faker->unique()->numerify('########'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(5),
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trial,
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
            'price_paid' => 0,
        ]);
    }
}
