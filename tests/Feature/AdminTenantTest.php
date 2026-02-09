<?php

use App\Models\Tenant;
use App\Models\User;

test('super admin can access tenant management', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.tenants'));
    $response->assertOk();
});

test('non-super-admin cannot access tenant management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.tenants'));
    $response->assertForbidden();
});

test('super admin can access staff management', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.staff'));
    $response->assertOk();
});

test('guests cannot access admin pages', function () {
    $response = $this->get(route('admin.tenants'));
    $response->assertRedirect(route('login'));
});
