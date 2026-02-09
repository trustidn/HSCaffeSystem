<?php

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\Tenant;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('marks table as occupied when order is confirmed', function () {
    $tenant = Tenant::factory()->create();
    $table = Table::factory()->create(['tenant_id' => $tenant->id, 'status' => 'available']);

    $order = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0001',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Pending->value,
        'payment_status' => 'unpaid',
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Available);

    $order->update([
        'status' => OrderStatus::Confirmed->value,
        'confirmed_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Occupied);
});

it('marks table as occupied when POS creates confirmed order', function () {
    $tenant = Tenant::factory()->create();
    $table = Table::factory()->create(['tenant_id' => $tenant->id, 'status' => 'available']);

    Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0002',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Confirmed->value,
        'payment_status' => 'unpaid',
        'confirmed_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Occupied);
});

it('releases table when order is completed and no other active orders', function () {
    $tenant = Tenant::factory()->create();
    $table = Table::factory()->create(['tenant_id' => $tenant->id, 'status' => 'occupied']);

    $order = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0003',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Served->value,
        'payment_status' => 'paid',
    ]);

    $order->update([
        'status' => OrderStatus::Completed->value,
        'completed_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Available);
});

it('releases table when order is cancelled and no other active orders', function () {
    $tenant = Tenant::factory()->create();
    $table = Table::factory()->create(['tenant_id' => $tenant->id, 'status' => 'occupied']);

    $order = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0004',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Pending->value,
        'payment_status' => 'unpaid',
    ]);

    $order->update([
        'status' => OrderStatus::Cancelled->value,
        'cancelled_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Available);
});

it('keeps table occupied if other active orders still exist', function () {
    $tenant = Tenant::factory()->create();
    $table = Table::factory()->create(['tenant_id' => $tenant->id, 'status' => 'occupied']);

    $order1 = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0005',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Preparing->value,
        'payment_status' => 'unpaid',
    ]);

    $order2 = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0006',
        'table_id' => $table->id,
        'type' => 'dine_in',
        'status' => OrderStatus::Confirmed->value,
        'payment_status' => 'unpaid',
    ]);

    // Complete order1, but order2 is still active
    $order1->update([
        'status' => OrderStatus::Completed->value,
        'completed_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Occupied);

    // Now complete order2 too — table should be released
    $order2->update([
        'status' => OrderStatus::Completed->value,
        'completed_at' => now(),
    ]);

    expect($table->fresh()->status)->toBe(TableStatus::Available);
});

it('does not change table status for orders without a table', function () {
    $tenant = Tenant::factory()->create();

    $order = Order::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0007',
        'table_id' => null,
        'type' => 'takeaway',
        'status' => OrderStatus::Pending->value,
        'payment_status' => 'unpaid',
    ]);

    // Should not throw any errors
    $order->update([
        'status' => OrderStatus::Confirmed->value,
        'confirmed_at' => now(),
    ]);

    expect(true)->toBeTrue();
});
