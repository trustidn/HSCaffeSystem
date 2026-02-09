<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;

test('tenant users can only see their own categories', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Category::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Cat A']);
    Category::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Cat B']);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    $categories = Category::all();
    expect($categories)->toHaveCount(1);
    expect($categories->first()->name)->toBe('Cat A');
});

test('tenant users can only see their own menu items', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $categoryA = Category::factory()->create(['tenant_id' => $tenantA->id]);
    $categoryB = Category::factory()->create(['tenant_id' => $tenantB->id]);

    MenuItem::factory()->create(['tenant_id' => $tenantA->id, 'category_id' => $categoryA->id, 'name' => 'Menu A']);
    MenuItem::factory()->create(['tenant_id' => $tenantB->id, 'category_id' => $categoryB->id, 'name' => 'Menu B']);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    $items = MenuItem::all();
    expect($items)->toHaveCount(1);
    expect($items->first()->name)->toBe('Menu A');
});

test('tenant users can only see their own tables', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Table::factory()->create(['tenant_id' => $tenantA->id, 'number' => 'A1']);
    Table::factory()->create(['tenant_id' => $tenantB->id, 'number' => 'B1']);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    $tables = Table::all();
    expect($tables)->toHaveCount(1);
    expect($tables->first()->number)->toBe('A1');
});

test('tenant users can only see their own orders', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Order::factory()->create(['tenant_id' => $tenantA->id]);
    Order::factory()->create(['tenant_id' => $tenantB->id]);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    expect(Order::count())->toBe(1);
});

test('tenant users can only see their own customers', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Customer::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Cust A']);
    Customer::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Cust B']);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    $customers = Customer::all();
    expect($customers)->toHaveCount(1);
    expect($customers->first()->name)->toBe('Cust A');
});

test('tenant users can only see their own ingredients', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Ingredient::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Ing A']);
    Ingredient::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Ing B']);

    $userA = User::factory()->owner($tenantA)->create();
    $this->actingAs($userA);

    $ingredients = Ingredient::all();
    expect($ingredients)->toHaveCount(1);
    expect($ingredients->first()->name)->toBe('Ing A');
});

test('super admin can see all tenants data', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Category::factory()->create(['tenant_id' => $tenantA->id]);
    Category::factory()->create(['tenant_id' => $tenantB->id]);

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    expect(Category::count())->toBe(2);
});

test('auto-assigns tenant_id on model creation', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $category = Category::create(['name' => 'Auto Tenant', 'slug' => 'auto-tenant', 'sort_order' => 1]);

    expect($category->tenant_id)->toBe($tenant->id);
});
