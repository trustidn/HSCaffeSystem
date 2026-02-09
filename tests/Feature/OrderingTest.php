<?php

use App\Models\Tenant;
use App\Models\User;

test('cashier can access POS', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('pos.index'));
    $response->assertOk();
});

test('owner can access orders page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('orders.index'));
    $response->assertOk();
});

test('kitchen staff cannot access POS', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('pos.index'));
    $response->assertForbidden();
});

test('public order page loads for active tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cafe']);

    $response = $this->get(route('public.order', ['tenantSlug' => 'test-cafe']));
    $response->assertOk();
});

test('public order page returns 404 for unknown tenant', function () {
    $response = $this->get(route('public.order', ['tenantSlug' => 'nonexistent']));
    $response->assertNotFound();
});
