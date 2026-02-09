<?php

use App\Models\AuditLog;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows super admin to access audit log page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.audit-logs'))
        ->assertSuccessful();
});

it('prevents non-super admin from accessing audit log page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('admin.audit-logs'))
        ->assertForbidden();
});

it('records audit log entry via model helper', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user);

    AuditLog::record('test_action', 'Test description', ['key' => 'value']);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'test_action',
        'description' => 'Test description',
    ]);

    $log = AuditLog::first();
    expect($log->metadata)->toBe(['key' => 'value']);
});

it('displays audit logs in table', function () {
    $user = User::factory()->superAdmin()->create();

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'staff_create',
        'description' => 'User dibuat: test@example.com',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($user);

    \Livewire\Livewire::test('pages::admin.audit-logs')
        ->assertSee('User dibuat: test@example.com')
        ->assertSee('Staff Dibuat');
});

it('filters audit logs by action', function () {
    $user = User::factory()->superAdmin()->create();

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'staff_create',
        'description' => 'User dibuat: staff@test.com',
    ]);

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'tenant_delete',
        'description' => 'Cafe dihapus: Test Cafe',
    ]);

    $this->actingAs($user);

    \Livewire\Livewire::test('pages::admin.audit-logs')
        ->set('filterAction', 'staff_create')
        ->assertSee('staff@test.com')
        ->assertDontSee('Test Cafe');
});

it('searches audit logs by description', function () {
    $user = User::factory()->superAdmin()->create();

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'staff_create',
        'description' => 'User dibuat: specific@email.com',
    ]);

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'tenant_create',
        'description' => 'Cafe dibuat: Random Cafe',
    ]);

    $this->actingAs($user);

    \Livewire\Livewire::test('pages::admin.audit-logs')
        ->set('search', 'specific@email.com')
        ->assertSee('specific@email.com')
        ->assertDontSee('Random Cafe');
});
