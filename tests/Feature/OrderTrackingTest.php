<?php

use App\Models\Order;
use App\Models\Tenant;

test('public track page loads for active tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'track-cafe']);

    $response = $this->get(route('public.track', ['tenantSlug' => 'track-cafe']));
    $response->assertOk();
    $response->assertSee('Lacak Status Pesanan');
});

test('public track page returns 404 for unknown tenant', function () {
    $response = $this->get(route('public.track', ['tenantSlug' => 'nonexistent']));
    $response->assertNotFound();
});

test('track page auto-searches with query parameter', function () {
    $tenant = Tenant::factory()->create(['slug' => 'track-cafe']);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-TEST-0001',
    ]);

    $response = $this->get(route('public.track', ['tenantSlug' => 'track-cafe']).'?q=ORD-TEST-0001');
    $response->assertOk();
    $response->assertSee('ORD-TEST-0001');
});
