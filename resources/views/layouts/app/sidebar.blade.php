<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            {{-- Tenant & Role Info --}}
            @if (auth()->user()->tenant)
                <div class="mx-3 mb-2 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="text-sm font-semibold truncate">{{ auth()->user()->tenant->name }}</div>
                    <div class="mt-0.5 text-xs text-zinc-500">{{ auth()->user()->role->label() }}</div>
                </div>
            @elseif (auth()->user()->isSuperAdmin())
                <div class="mx-3 mb-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800 dark:bg-indigo-900/20">
                    <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">Super Admin</div>
                    <div class="mt-0.5 text-xs text-indigo-500 dark:text-indigo-400">{{ __('Manajemen Platform') }}</div>
                </div>
            @endif

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Super Admin Navigation --}}
                @if (auth()->user()->isSuperAdmin())
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="building-storefront" :href="route('admin.tenants')" :current="request()->routeIs('admin.tenants*')" wire:navigate>
                            {{ __('Kelola Cafe') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="credit-card" :href="route('admin.subscriptions')" :current="request()->routeIs('admin.subscriptions')" wire:navigate>
                            {{ __('Paket Langganan') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.staff')" :current="request()->routeIs('admin.staff')" wire:navigate>
                            {{ __('Kelola Staff') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="megaphone" :href="route('admin.announcements')" :current="request()->routeIs('admin.announcements')" wire:navigate>
                            {{ __('Pengumuman') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="circle-stack" :href="route('admin.backups')" :current="request()->routeIs('admin.backups')" wire:navigate>
                            {{ __('Backup Database') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit-logs')" :current="request()->routeIs('admin.audit-logs')" wire:navigate>
                            {{ __('Audit Log') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-8-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>
                            {{ __('Pengaturan Platform') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                {{-- Management Navigation (Owner, Manager) --}}
                @if (auth()->user()->isManagement() && ! auth()->user()->isSuperAdmin())
                    <flux:sidebar.group :heading="__('Manajemen')" class="grid">
                        @if (Route::has('menu.index'))
                            <flux:sidebar.item icon="rectangle-group" :href="route('menu.index')" :current="request()->routeIs('menu.*')" wire:navigate>
                                {{ __('Menu') }}
                            </flux:sidebar.item>
                        @endif
                        @if (Route::has('tables.index'))
                            <flux:sidebar.item icon="table-cells" :href="route('tables.index')" :current="request()->routeIs('tables.*')" wire:navigate>
                                {{ __('Meja') }}
                            </flux:sidebar.item>
                        @endif
                        @if (Route::has('inventory.index'))
                            <flux:sidebar.item icon="cube" :href="route('inventory.index')" :current="request()->routeIs('inventory.*')" wire:navigate>
                                {{ __('Inventaris') }}
                            </flux:sidebar.item>
                        @endif
                        @if (Route::has('staff.index'))
                            <flux:sidebar.item icon="users" :href="route('staff.index')" :current="request()->routeIs('staff.*')" wire:navigate>
                                {{ __('Staff') }}
                            </flux:sidebar.item>
                        @endif
                        @if (Route::has('customers.index'))
                            <flux:sidebar.item icon="user-group" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>
                                {{ __('Pelanggan') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                {{-- Operations Navigation --}}
                @if (auth()->user()->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Cashier, \App\Enums\UserRole::Waiter))
                    @if (Route::has('pos.index') || Route::has('orders.index'))
                        <flux:sidebar.group :heading="__('Operasional')" class="grid">
                            @if (Route::has('pos.index'))
                                <flux:sidebar.item icon="shopping-cart" :href="route('pos.index')" :current="request()->routeIs('pos.*')" wire:navigate>
                                    {{ __('POS / Kasir') }}
                                </flux:sidebar.item>
                            @endif
                            @if (Route::has('orders.index'))
                                <flux:sidebar.item icon="queue-list" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>
                                    <span class="flex items-center gap-2">
                                        {{ __('Pesanan') }}
                                        @php
                                            $pendingCount = \App\Models\Order::where('status', \App\Enums\OrderStatus::Pending->value)->count();
                                        @endphp
                                        @if ($pendingCount > 0)
                                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $pendingCount }}</span>
                                        @endif
                                    </span>
                                </flux:sidebar.item>
                            @endif
                        </flux:sidebar.group>
                    @endif
                @endif

                {{-- Kitchen Navigation --}}
                @if (auth()->user()->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Kitchen))
                    @if (Route::has('kitchen.index'))
                        <flux:sidebar.group :heading="__('Dapur')" class="grid">
                            <flux:sidebar.item icon="fire" :href="route('kitchen.index')" :current="request()->routeIs('kitchen.*')" wire:navigate>
                                {{ __('Kitchen Display') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endif

                {{-- Reports Navigation --}}
                @if (auth()->user()->isManagement() && ! auth()->user()->isSuperAdmin())
                    @if (Route::has('reports.sales'))
                        <flux:sidebar.group :heading="__('Laporan')" class="grid">
                            <flux:sidebar.item icon="chart-bar" :href="route('reports.sales')" :current="request()->routeIs('reports.*')" wire:navigate>
                                {{ __('Laporan') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
