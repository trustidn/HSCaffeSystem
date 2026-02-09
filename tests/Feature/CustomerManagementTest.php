<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('owner can access customer management page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('customers.index'));
    $response->assertOk();
});

test('cashier cannot access customer management page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('customers.index'));
    $response->assertForbidden();
});

test('owner can create a customer', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::customers.index')
        ->set('name', 'John Doe')
        ->set('phone', '08123456789')
        ->set('email', 'john@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'phone' => '08123456789',
    ]);
});

test('owner can edit a customer', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);

    Livewire::test('pages::customers.index')
        ->call('edit', $customer->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'New Name',
    ]);
});

test('owner can delete a customer', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::test('pages::customers.index')
        ->call('delete', $customer->id);

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});

test('owner can view customer detail', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::test('pages::customers.index')
        ->call('showCustomerDetail', $customer->id)
        ->assertSet('showDetail', true)
        ->assertSet('detailId', $customer->id);
});
