<?php

use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('owner can access cafe settings page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('cafe.edit'));
    $response->assertOk();
});

test('manager can access cafe settings page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->manager($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('cafe.edit'));
    $response->assertOk();
});

test('cashier cannot access cafe settings page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('cafe.edit'));
    $response->assertForbidden();
});

test('owner can update cafe name and slogan', function () {
    $tenant = Tenant::factory()->create(['name' => 'Old Name', 'slogan' => null]);
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.cafe')
        ->set('name', 'New Cafe Name')
        ->set('slogan', 'Kopi terbaik di kota')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('cafe-updated');

    $tenant->refresh();
    expect($tenant->name)->toBe('New Cafe Name');
    expect($tenant->slogan)->toBe('Kopi terbaik di kota');
});

test('owner can update cafe colors', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.cafe')
        ->set('primaryColor', '#FF5733')
        ->set('secondaryColor', '#33FF57')
        ->call('save')
        ->assertHasNoErrors();

    $tenant->refresh();
    expect($tenant->primary_color)->toBe('#FF5733');
    expect($tenant->secondary_color)->toBe('#33FF57');
});

test('owner can update cafe contact info', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.cafe')
        ->set('address', 'Jl. Baru No. 99')
        ->set('phone', '021-9999999')
        ->set('email', 'new@cafe.id')
        ->call('save')
        ->assertHasNoErrors();

    $tenant->refresh();
    expect($tenant->address)->toBe('Jl. Baru No. 99');
    expect($tenant->phone)->toBe('021-9999999');
    expect($tenant->email)->toBe('new@cafe.id');
});

test('cafe name is required', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.cafe')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('invalid color format is rejected', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.cafe')
        ->set('primaryColor', 'not-a-color')
        ->call('save')
        ->assertHasErrors(['primaryColor']);
});
