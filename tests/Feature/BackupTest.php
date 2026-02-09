<?php

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// --- Backup Tests ---

it('allows super admin to access backup page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.backups'))
        ->assertSuccessful();
});

it('prevents non-super admin from accessing backup page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('admin.backups'))
        ->assertForbidden();
});

it('allows super admin to create full database backup', function () {
    $user = User::factory()->superAdmin()->create();

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->set('backupType', 'full')
        ->call('createBackup')
        ->assertHasNoErrors();

    // Check that a backup file was created
    $files = Storage::disk('local')->files('backups');
    $sqliteFiles = array_filter($files, fn ($f) => str_contains($f, '_full_') && str_ends_with($f, '.sqlite'));

    expect($sqliteFiles)->not->toBeEmpty();

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});

it('allows super admin to create per-tenant backup', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Test Cafe', 'slug' => 'test-cafe']);

    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Minuman']);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->set('backupType', 'tenant')
        ->set('selectedTenantId', (string) $tenant->id)
        ->call('createBackup')
        ->assertHasNoErrors();

    // Check that a JSON backup file was created
    $files = Storage::disk('local')->files('backups');
    $jsonFiles = array_filter($files, fn ($f) => str_contains($f, '_tenant_test-cafe_') && str_ends_with($f, '.json'));

    expect($jsonFiles)->not->toBeEmpty();

    // Verify JSON content
    $backupPath = array_values($jsonFiles)[0];
    $data = json_decode(Storage::disk('local')->get($backupPath), true);

    expect($data['metadata']['tenant_name'])->toBe('Test Cafe');
    expect($data['categories'])->toHaveCount(1);

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});

it('validates tenant selection for per-tenant backup', function () {
    $user = User::factory()->superAdmin()->create();

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->set('backupType', 'tenant')
        ->set('selectedTenantId', '')
        ->call('createBackup')
        ->assertHasErrors('selectedTenantId');
});

it('allows super admin to delete a backup', function () {
    $user = User::factory()->superAdmin()->create();

    // Create a dummy backup file
    Storage::disk('local')->put('backups/test_backup.json', '{}');

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->call('deleteBackup', 'backups/test_backup.json');

    expect(Storage::disk('local')->exists('backups/test_backup.json'))->toBeFalse();

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});

// --- Restore Tests ---

it('requires RESTORE confirmation text to execute restore', function () {
    $user = User::factory()->superAdmin()->create();

    Storage::disk('local')->put('backups/test_full.sqlite', 'dummy');

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->call('openRestoreModal', 'backups/test_full.sqlite')
        ->assertSet('showRestoreModal', true)
        ->set('restoreConfirmText', 'wrong')
        ->call('executeRestore')
        ->assertHasErrors('restoreConfirmText');

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});

it('allows super admin to open restore modal for full backup', function () {
    $user = User::factory()->superAdmin()->create();

    // First create a backup
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->set('backupType', 'full')
        ->call('createBackup')
        ->assertHasNoErrors();

    // Get the backup file path
    $files = Storage::disk('local')->files('backups');
    $backupFile = collect($files)->first(fn ($f) => str_contains($f, '_full_') && str_ends_with($f, '.sqlite'));

    expect($backupFile)->not->toBeNull();

    // Verify modal opens correctly with the right type
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->call('openRestoreModal', $backupFile)
        ->assertSet('showRestoreModal', true)
        ->assertSet('restoreType', 'full')
        ->assertSet('restoreFilename', basename($backupFile));

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});

it('allows super admin to restore per-tenant backup', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Restore Cafe', 'slug' => 'restore-cafe']);

    // Create some data for the tenant
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kopi']);
    $ingredient = Ingredient::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Biji Kopi']);

    // Create a tenant backup
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->set('backupType', 'tenant')
        ->set('selectedTenantId', (string) $tenant->id)
        ->call('createBackup')
        ->assertHasNoErrors();

    // Delete the original data to simulate data loss
    DB::table('ingredients')->where('tenant_id', $tenant->id)->delete();
    DB::table('categories')->where('tenant_id', $tenant->id)->delete();

    expect(DB::table('categories')->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(DB::table('ingredients')->where('tenant_id', $tenant->id)->count())->toBe(0);

    // Find the backup file
    $files = Storage::disk('local')->files('backups');
    $backupFile = collect($files)->first(fn ($f) => str_contains($f, '_tenant_restore-cafe_'));

    expect($backupFile)->not->toBeNull();

    // Execute restore
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.backups')
        ->call('openRestoreModal', $backupFile)
        ->assertSet('restoreType', 'tenant')
        ->set('restoreTenantId', (string) $tenant->id)
        ->set('restoreConfirmText', 'RESTORE')
        ->call('executeRestore')
        ->assertHasNoErrors()
        ->assertSet('showRestoreModal', false);

    // Verify data was restored
    expect(DB::table('categories')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(DB::table('categories')->where('tenant_id', $tenant->id)->value('name'))->toBe('Kopi');
    expect(DB::table('ingredients')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(DB::table('ingredients')->where('tenant_id', $tenant->id)->value('name'))->toBe('Biji Kopi');

    // Cleanup
    Storage::disk('local')->deleteDirectory('backups');
});
