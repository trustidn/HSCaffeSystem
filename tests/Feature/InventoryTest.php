<?php

use App\Models\Tenant;
use App\Models\User;

test('owner can access inventory management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('inventory.index'));
    $response->assertOk();
});

test('manager can access inventory management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->manager($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('inventory.index'));
    $response->assertOk();
});

test('cashier cannot access inventory', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('inventory.index'));
    $response->assertForbidden();
});
