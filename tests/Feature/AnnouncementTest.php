<?php

use App\Models\Announcement;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows super admin to access announcements page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.announcements'))
        ->assertSuccessful();
});

it('prevents non-super admin from accessing announcements page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('admin.announcements'))
        ->assertForbidden();
});

it('allows super admin to create an announcement', function () {
    $user = User::factory()->superAdmin()->create();

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.announcements')
        ->call('openCreateModal')
        ->set('title', 'Fitur baru: Kitchen Display')
        ->set('content', 'Kami telah menambahkan Kitchen Display System untuk memudahkan pengelolaan dapur.')
        ->set('type', 'update')
        ->set('publishedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    $this->assertDatabaseHas('announcements', [
        'title' => 'Fitur baru: Kitchen Display',
        'type' => 'update',
        'created_by' => $user->id,
    ]);
});

it('allows super admin to edit an announcement', function () {
    $user = User::factory()->superAdmin()->create();
    $announcement = Announcement::create([
        'title' => 'Original Title',
        'content' => 'Original content.',
        'type' => 'info',
        'is_active' => true,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.announcements')
        ->call('openEditModal', $announcement->id)
        ->set('title', 'Updated Title')
        ->call('save');

    expect($announcement->fresh()->title)->toBe('Updated Title');
});

it('allows super admin to delete an announcement', function () {
    $user = User::factory()->superAdmin()->create();
    $announcement = Announcement::create([
        'title' => 'To Delete',
        'content' => 'Will be deleted.',
        'type' => 'info',
        'is_active' => true,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.announcements')
        ->call('confirmDelete', $announcement->id)
        ->call('deleteAnnouncement');

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('allows super admin to toggle announcement active status', function () {
    $user = User::factory()->superAdmin()->create();
    $announcement = Announcement::create([
        'title' => 'Toggle Test',
        'content' => 'Toggle content.',
        'type' => 'info',
        'is_active' => true,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.announcements')
        ->call('toggleActive', $announcement->id);

    expect($announcement->fresh()->is_active)->toBeFalse();
});

it('shows published announcements on dashboard', function () {
    $admin = User::factory()->superAdmin()->create();
    Announcement::create([
        'title' => 'Visible Announcement',
        'content' => 'This should be visible.',
        'type' => 'update',
        'is_active' => true,
        'published_at' => now()->subHour(),
        'created_by' => $admin->id,
    ]);

    $owner = User::factory()->owner()->create();

    \Livewire\Livewire::actingAs($owner)
        ->test('pages::dashboard')
        ->assertSee('Visible Announcement');
});

it('does not show inactive announcements on dashboard', function () {
    $admin = User::factory()->superAdmin()->create();
    Announcement::create([
        'title' => 'Hidden Announcement',
        'content' => 'This should NOT be visible.',
        'type' => 'info',
        'is_active' => false,
        'published_at' => now()->subHour(),
        'created_by' => $admin->id,
    ]);

    $owner = User::factory()->owner()->create();

    \Livewire\Livewire::actingAs($owner)
        ->test('pages::dashboard')
        ->assertDontSee('Hidden Announcement');
});
