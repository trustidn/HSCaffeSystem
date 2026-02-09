<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionPlan> */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Paket '.$this->faker->word(),
            'duration_months' => $this->faker->randomElement([1, 3, 6, 12]),
            'price' => $this->faker->randomElement([199000, 499000, 899000, 1499000]),
            'description' => $this->faker->sentence(),
            'features' => ['Menu Management', 'POS', 'Kitchen Display'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
