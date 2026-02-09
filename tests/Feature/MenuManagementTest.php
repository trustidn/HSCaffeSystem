<?php

use App\Models\Tenant;
use App\Models\User;

test('owners can access menu management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('menu.index'));
    $response->assertOk();
});

test('owners can access table management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('tables.index'));
    $response->assertOk();
});

test('owners can access staff management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('staff.index'));
    $response->assertOk();
});

test('cashiers cannot access menu management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('menu.index'));
    $response->assertForbidden();
});
