<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('kitchen staff can access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertOk();
});

test('cashier cannot access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertForbidden();
});

test('owner can access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertOk();
});

test('kitchen display shows pending orders', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    Livewire::test('pages::kitchen.index')
        ->assertSee($order->order_number);
});

test('kitchen staff can confirm a pending order', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    Livewire::test('pages::kitchen.index')
        ->call('confirmOrder', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('kitchen staff can reject a pending order', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    Livewire::test('pages::kitchen.index')
        ->call('rejectOrder', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('full order flow through kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    $component = Livewire::test('pages::kitchen.index');

    // Confirm
    $component->call('confirmOrder', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);

    // Start preparing
    $component->call('startPreparing', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    // Mark ready
    $component->call('markReady', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    // Mark served
    $component->call('markServed', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Served);
});
