<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

test('owner can access reports page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.sales'));
    $response->assertOk();
});

test('manager can access reports page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->manager($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.sales'));
    $response->assertOk();
});

test('cashier cannot access reports page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.sales'));
    $response->assertForbidden();
});

test('reports page shows sales summary', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 50000,
    ]);

    $response = $this->get(route('reports.sales'));
    $response->assertOk();
    $response->assertSee('Total Pesanan');
    $response->assertSee('Total Pendapatan');
});

test('reports page shows stock tab', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.sales'));
    $response->assertOk();
    $response->assertSee('Stok');
});
