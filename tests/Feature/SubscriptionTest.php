<?php

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

// --- Subscription Plan Management (Super Admin) ---

test('super admin can access subscription plans page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.subscriptions'))
        ->assertOk();
});

test('non-admin cannot access subscription plans page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();

    $this->actingAs($user)
        ->get(route('admin.subscriptions'))
        ->assertForbidden();
});

test('super admin can create a subscription plan', function () {
    $user = User::factory()->superAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.subscriptions')
        ->call('openCreateModal')
        ->set('name', 'Paket Bulanan')
        ->set('durationMonths', 1)
        ->set('price', '199000')
        ->set('description', 'Paket bulanan basic')
        ->set('features', "Menu Management\nPOS / Kasir")
        ->call('savePlan');

    expect(SubscriptionPlan::where('name', 'Paket Bulanan')->exists())->toBeTrue();

    $plan = SubscriptionPlan::where('name', 'Paket Bulanan')->first();
    expect($plan->duration_months)->toBe(1);
    expect($plan->features)->toBeArray()->toHaveCount(2);
});

test('super admin can update a subscription plan', function () {
    $user = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Old Name', 'price' => 100000]);

    Livewire::actingAs($user)
        ->test('pages::admin.subscriptions')
        ->call('openEditModal', $plan->id)
        ->set('name', 'New Name')
        ->set('price', '250000')
        ->call('savePlan');

    $plan->refresh();
    expect($plan->name)->toBe('New Name');
    expect((float) $plan->price)->toBe(250000.00);
});

test('super admin can toggle plan active status', function () {
    $user = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    Livewire::actingAs($user)
        ->test('pages::admin.subscriptions')
        ->call('toggleActive', $plan->id);

    expect($plan->fresh()->is_active)->toBeFalse();
});

test('super admin can delete a plan without subscriptions', function () {
    $user = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.subscriptions')
        ->call('confirmDelete', $plan->id)
        ->call('deletePlan');

    expect(SubscriptionPlan::find($plan->id))->toBeNull();
});

test('super admin cannot delete a plan with active subscriptions', function () {
    $user = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create();
    $tenant = Tenant::factory()->create();

    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.subscriptions')
        ->call('confirmDelete', $plan->id)
        ->call('deletePlan');

    expect(SubscriptionPlan::find($plan->id))->not->toBeNull();
});

// --- Tenant Subscription Management ---

test('super admin can access tenant subscription page', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.tenant.subscription', $tenant))
        ->assertOk();
});

test('super admin can add subscription to a tenant', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->withoutSubscription()->create();
    $plan = SubscriptionPlan::factory()->create(['duration_months' => 3, 'price' => 499000]);

    Livewire::actingAs($user)
        ->test('pages::admin.tenant-subscription', ['tenant' => $tenant])
        ->call('openAddModal')
        ->set('selectedPlanId', $plan->id)
        ->set('startsAt', now()->format('Y-m-d'))
        ->set('pricePaid', '499000')
        ->set('paymentReference', 'TRX-001')
        ->call('addSubscription');

    expect(Subscription::where('tenant_id', $tenant->id)->count())->toBe(1);

    $sub = Subscription::where('tenant_id', $tenant->id)->first();
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->payment_reference)->toBe('TRX-001');
});

test('adding new subscription expires previous active subscription', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $plan = SubscriptionPlan::factory()->create(['duration_months' => 1]);

    $oldSub = Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $newPlan = SubscriptionPlan::factory()->create(['duration_months' => 6]);

    Livewire::actingAs($user)
        ->test('pages::admin.tenant-subscription', ['tenant' => $tenant])
        ->call('openAddModal')
        ->set('selectedPlanId', $newPlan->id)
        ->set('startsAt', now()->format('Y-m-d'))
        ->set('pricePaid', '899000')
        ->call('addSubscription');

    expect($oldSub->fresh()->status)->toBe(SubscriptionStatus::Expired);
    expect(Subscription::where('tenant_id', $tenant->id)->where('status', SubscriptionStatus::Active->value)->count())->toBe(1);
});

// --- Subscription Middleware ---

test('tenant with active subscription can access operational routes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $this->actingAs($user)
        ->get(route('menu.index'))
        ->assertOk();
});

test('tenant without active subscription is redirected to dashboard', function () {
    $tenant = Tenant::factory()->withoutSubscription()->create();
    $user = User::factory()->owner($tenant)->create();

    $this->actingAs($user)
        ->get(route('menu.index'))
        ->assertRedirect(route('dashboard'));
});

test('super admin bypasses subscription check', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.subscriptions'))
        ->assertOk();
});

// --- Model Methods ---

test('subscription can check if active', function () {
    $plan = SubscriptionPlan::factory()->create();
    $tenant = Tenant::factory()->create();

    $activeSub = Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $expiredSub = Subscription::factory()->expired()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    expect($activeSub->isActive())->toBeTrue();
    expect($expiredSub->isActive())->toBeFalse();
});

test('subscription can check if expiring soon', function () {
    $plan = SubscriptionPlan::factory()->create();
    $tenant = Tenant::factory()->create();

    $soonSub = Subscription::create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
        'starts_at' => now()->subDays(25),
        'ends_at' => now()->addDays(5),
        'price_paid' => 199000,
        'status' => SubscriptionStatus::Active,
    ]);

    $safeSub = Subscription::create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
        'price_paid' => 199000,
        'status' => SubscriptionStatus::Active,
    ]);

    expect($soonSub->isExpiringSoon())->toBeTrue();
    expect($safeSub->isExpiringSoon())->toBeFalse();
});

test('subscription plan has correct duration label', function () {
    expect(SubscriptionPlan::factory()->create(['duration_months' => 1])->durationLabel())->toBe('1 Bulan');
    expect(SubscriptionPlan::factory()->create(['duration_months' => 3])->durationLabel())->toBe('3 Bulan');
    expect(SubscriptionPlan::factory()->create(['duration_months' => 6])->durationLabel())->toBe('6 Bulan');
    expect(SubscriptionPlan::factory()->create(['duration_months' => 12])->durationLabel())->toBe('1 Tahun');
});

test('tenant knows if it has active subscription', function () {
    $tenant = Tenant::factory()->withoutSubscription()->create();
    $plan = SubscriptionPlan::factory()->create();

    expect($tenant->hasActiveSubscription())->toBeFalse();

    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    // Refresh the relation
    $tenant->unsetRelation('activeSubscription');
    expect($tenant->hasActiveSubscription())->toBeTrue();
});
