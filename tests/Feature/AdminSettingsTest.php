<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows super admin to access admin settings page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.settings'))
        ->assertSuccessful();
});

it('prevents non-super admin from accessing admin settings page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('allows super admin to save whatsapp number', function () {
    $user = User::factory()->superAdmin()->create();

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->set('whatsappNumber', '6281234567890')
        ->set('siteName', 'Test Cafe')
        ->call('save')
        ->assertDispatched('settings-saved');

    expect(SystemSetting::get('whatsapp_number'))->toBe('6281234567890');
    expect(SystemSetting::get('site_name'))->toBe('Test Cafe');
});

it('allows super admin to upload and remove site logo', function () {
    Storage::fake('public');

    $user = User::factory()->superAdmin()->create();

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->set('siteName', 'Test Cafe')
        ->set('siteLogo', $file)
        ->call('save')
        ->assertDispatched('settings-saved');

    $logoPath = SystemSetting::get('site_logo');
    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);

    // Remove logo
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->call('removeSiteLogo');

    expect(SystemSetting::get('site_logo'))->toBeNull();
});

it('allows super admin to upload and remove favicon', function () {
    Storage::fake('public');

    $user = User::factory()->superAdmin()->create();

    $file = UploadedFile::fake()->image('favicon.png', 64, 64);

    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->set('siteName', 'Test Cafe')
        ->set('siteFavicon', $file)
        ->call('save')
        ->assertDispatched('settings-saved');

    $faviconPath = SystemSetting::get('site_favicon');
    expect($faviconPath)->not->toBeNull();
    Storage::disk('public')->assertExists($faviconPath);

    // Remove favicon
    \Livewire\Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->call('removeSiteFavicon');

    expect(SystemSetting::get('site_favicon'))->toBeNull();
});

it('shows whatsapp link on welcome page when number is set', function () {
    SystemSetting::set('whatsapp_number', '6281234567890');

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('wa.me/6281234567890');
});

it('does not show register link on welcome page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('Daftar');
});

it('prevents guests from accessing register route', function () {
    $this->get('/register')
        ->assertNotFound();
});
