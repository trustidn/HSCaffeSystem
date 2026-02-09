<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('cashier can add items to POS cart', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $item = MenuItem::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'base_price' => 25000,
    ]);

    Livewire::test('pages::pos.index')
        ->call('addToCart', $item->id)
        ->assertSet("cart.{$item->id}-0.quantity", 1)
        ->call('addToCart', $item->id)
        ->assertSet("cart.{$item->id}-0.quantity", 2);
});

test('cashier can place an order from POS', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $item = MenuItem::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'base_price' => 20000,
    ]);

    Livewire::test('pages::pos.index')
        ->call('addToCart', $item->id)
        ->set('orderType', 'takeaway')
        ->set('customerName', 'Test Customer')
        ->call('placeOrder')
        ->assertHasNoErrors();

    expect(Order::count())->toBe(1);
    $order = Order::first();
    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->items)->toHaveCount(1);
});

test('cashier can clear the cart', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $item = MenuItem::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
    ]);

    Livewire::test('pages::pos.index')
        ->call('addToCart', $item->id)
        ->call('clearCart')
        ->assertSet('cart', []);
});

test('kitchen staff can update order status', function () {
    $tenant = Tenant::factory()->create();
    $kitchenUser = User::factory()->kitchen($tenant)->create();
    $this->actingAs($kitchenUser);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Confirmed,
    ]);

    Livewire::test('pages::kitchen.index')
        ->call('startPreparing', $order->id);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Preparing);
});

test('kitchen staff can mark order as ready', function () {
    $tenant = Tenant::factory()->create();
    $kitchenUser = User::factory()->kitchen($tenant)->create();
    $this->actingAs($kitchenUser);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Preparing,
    ]);

    Livewire::test('pages::kitchen.index')
        ->call('markReady', $order->id);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Ready);
});

test('order status flow follows correct sequence', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    // Confirm
    $order->update(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);

    // Start preparing
    $order->update(['status' => OrderStatus::Preparing, 'preparing_at' => now()]);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    // Ready
    $order->update(['status' => OrderStatus::Ready, 'ready_at' => now()]);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    // Served
    $order->update(['status' => OrderStatus::Served, 'served_at' => now()]);
    expect($order->fresh()->status)->toBe(OrderStatus::Served);

    // Completed
    $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('order generates unique order numbers', function () {
    $tenant = Tenant::factory()->create();

    $number1 = Order::generateOrderNumber($tenant->id);
    Order::factory()->create(['tenant_id' => $tenant->id, 'order_number' => $number1]);

    $number2 = Order::generateOrderNumber($tenant->id);
    expect($number2)->not->toBe($number1);
});
