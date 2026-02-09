<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'whatsappNumber' => \App\Models\SystemSetting::get('whatsapp_number'),
        'siteName' => \App\Models\SystemSetting::get('site_name', 'HsCaffeSystem'),
        'siteTagline' => \App\Models\SystemSetting::get('site_tagline'),
        'siteLogo' => \App\Models\SystemSetting::get('site_logo'),
        'siteFavicon' => \App\Models\SystemSetting::get('site_favicon'),
        'plans' => \App\Models\SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->orderBy('duration_months')->get(),
    ]);
})->name('home');

Route::livewire('dashboard', 'pages::dashboard')
    ->middleware(['auth', 'verified', 'tenant'])
    ->name('dashboard');

Route::livewire('announcements', 'pages::announcements')
    ->middleware(['auth', 'verified'])
    ->name('announcements');

// Admin routes (Super Admin only)
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::livewire('/tenants', 'pages::admin.tenants')->name('admin.tenants');
    Route::livewire('/staff', 'pages::admin.staff')->name('admin.staff');
    Route::livewire('/subscriptions', 'pages::admin.subscriptions')->name('admin.subscriptions');
    Route::livewire('/tenants/{tenant}/subscription', 'pages::admin.tenant-subscription')->name('admin.tenant.subscription');
    Route::livewire('/settings', 'pages::admin.settings')->name('admin.settings');
    Route::livewire('/announcements', 'pages::admin.announcements')->name('admin.announcements');
    Route::livewire('/backups', 'pages::admin.backups')->name('admin.backups');
    Route::livewire('/audit-logs', 'pages::admin.audit-logs')->name('admin.audit-logs');
});

// Tenant management routes (Owner, Manager)
Route::middleware(['auth', 'verified', 'tenant', 'subscription', 'role:owner,manager'])->group(function () {
    Route::livewire('/menu', 'pages::menu.index')->name('menu.index');
    Route::livewire('/tables', 'pages::tables.index')->name('tables.index');
    Route::livewire('/staff', 'pages::staff.index')->name('staff.index');
    Route::livewire('/inventory', 'pages::inventory.index')->name('inventory.index');
    Route::livewire('/customers', 'pages::customers.index')->name('customers.index');
    Route::livewire('/reports', 'pages::reports.sales')->name('reports.sales');
});

// POS & Orders (Cashier, Waiter, Owner, Manager)
Route::middleware(['auth', 'verified', 'tenant', 'subscription', 'role:owner,manager,cashier,waiter'])->group(function () {
    Route::livewire('/pos', 'pages::pos.index')->name('pos.index');
    Route::livewire('/orders', 'pages::orders.index')->name('orders.index');
});

// Kitchen Display (Kitchen, Owner, Manager)
Route::middleware(['auth', 'verified', 'tenant', 'subscription', 'role:owner,manager,kitchen'])->group(function () {
    Route::livewire('/kitchen', 'pages::kitchen.index')->name('kitchen.index');
});

// Public customer-facing pages (no auth required, rate limited)
Route::livewire('/order/{tenantSlug}', 'pages::public.order')
    ->middleware('throttle:public-order')
    ->name('public.order');
Route::livewire('/track/{tenantSlug}', 'pages::public.track')
    ->middleware('throttle:public-track')
    ->name('public.track');

require __DIR__.'/settings.php';
