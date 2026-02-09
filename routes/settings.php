<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::livewire('settings/password', 'pages::settings.password')->name('user-password.edit');
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    // Cafe branding settings (Owner, Manager only)
    Route::livewire('settings/cafe', 'pages::settings.cafe')
        ->middleware(['tenant', 'role:owner,manager'])
        ->name('cafe.edit');
});
