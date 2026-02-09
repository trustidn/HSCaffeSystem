<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    private bool $skipSubscription = false;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Cafe';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'logo' => null,
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'tax_rate' => 11.00,
            'service_charge_rate' => 0.00,
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'operating_hours' => [
                'monday' => ['open' => '08:00', 'close' => '22:00'],
                'tuesday' => ['open' => '08:00', 'close' => '22:00'],
                'wednesday' => ['open' => '08:00', 'close' => '22:00'],
                'thursday' => ['open' => '08:00', 'close' => '22:00'],
                'friday' => ['open' => '08:00', 'close' => '23:00'],
                'saturday' => ['open' => '08:00', 'close' => '23:00'],
                'sunday' => ['open' => '09:00', 'close' => '21:00'],
            ],
            'is_active' => true,
            'settings' => [],
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            if ($this->skipSubscription) {
                return;
            }

            $plan = SubscriptionPlan::first() ?? SubscriptionPlan::create([
                'name' => 'Paket Test',
                'duration_months' => 1,
                'price' => 199000,
                'is_active' => true,
                'sort_order' => 0,
            ]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(25),
                'price_paid' => $plan->price,
                'status' => SubscriptionStatus::Active,
            ]);
        });
    }

    /**
     * Create tenant without an active subscription.
     */
    public function withoutSubscription(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $tenant->subscriptions()->delete();
        });
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
