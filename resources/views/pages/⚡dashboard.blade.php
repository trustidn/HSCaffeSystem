<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Announcement;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function isSuperAdmin(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    #[Computed]
    public function announcements()
    {
        return Announcement::published()
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function totalAnnouncements(): int
    {
        return Announcement::published()->count();
    }

    #[Computed]
    public function totalTenants(): int
    {
        return $this->isSuperAdmin ? Tenant::count() : 0;
    }

    #[Computed]
    public function totalUsers(): int
    {
        return $this->isSuperAdmin ? User::count() : 0;
    }

    #[Computed]
    public function platformRevenue(): float
    {
        return $this->isSuperAdmin
            ? (float) Order::withoutGlobalScopes()->where('payment_status', PaymentStatus::Paid->value)->sum('total')
            : 0;
    }

    #[Computed]
    public function todayOrders(): int
    {
        return $this->isSuperAdmin ? 0 : Order::whereDate('created_at', today())->count();
    }

    #[Computed]
    public function todayRevenue(): float
    {
        if ($this->isSuperAdmin) {
            return 0;
        }

        return (float) Order::whereDate('created_at', today())
            ->where('payment_status', PaymentStatus::Paid->value)
            ->sum('total');
    }

    #[Computed]
    public function pendingOrders(): int
    {
        if ($this->isSuperAdmin) {
            return 0;
        }

        return Order::whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
        ])->count();
    }

    #[Computed]
    public function lowStockCount(): int
    {
        if ($this->isSuperAdmin) {
            return 0;
        }

        return Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('is_active', true)
            ->count();
    }

    #[Computed]
    public function recentOrders()
    {
        if ($this->isSuperAdmin) {
            return collect();
        }

        return Order::with(['table', 'items'])
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function weeklyRevenue(): array
    {
        if ($this->isSuperAdmin) {
            return [];
        }

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $revenue = (float) Order::whereDate('created_at', $date)
                ->where('payment_status', PaymentStatus::Paid->value)
                ->sum('total');
            $data[] = [
                'date' => $date->format('D'),
                'revenue' => $revenue,
            ];
        }

        return $data;
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    {{-- Announcements --}}
    @if ($this->announcements->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:icon.megaphone class="size-4 text-zinc-500" />
                    <flux:heading size="sm">{{ __('Pengumuman Terbaru') }}</flux:heading>
                </div>
                @if ($this->totalAnnouncements > 3)
                    <flux:link :href="route('announcements')" wire:navigate class="text-xs">
                        {{ __('Lihat Semua') }} ({{ $this->totalAnnouncements }})
                    </flux:link>
                @endif
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                @foreach ($this->announcements as $announcement)
                    @php
                        $config = match($announcement->type) {
                            'update' => ['icon' => 'rocket-launch', 'iconColor' => 'text-indigo-600 dark:text-indigo-400', 'badge' => 'indigo', 'badgeLabel' => 'Update'],
                            'warning' => ['icon' => 'exclamation-triangle', 'iconColor' => 'text-amber-600 dark:text-amber-400', 'badge' => 'amber', 'badgeLabel' => 'Peringatan'],
                            'success' => ['icon' => 'check-circle', 'iconColor' => 'text-emerald-600 dark:text-emerald-400', 'badge' => 'emerald', 'badgeLabel' => 'Sukses'],
                            default => ['icon' => 'information-circle', 'iconColor' => 'text-sky-600 dark:text-sky-400', 'badge' => 'sky', 'badgeLabel' => 'Info'],
                        };
                    @endphp
                    <div class="flex gap-4 px-5 py-4">
                        <div class="shrink-0 pt-0.5">
                            <flux:icon :name="$config['icon']" class="size-5 {{ $config['iconColor'] }}" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $announcement->title }}</span>
                                <flux:badge size="sm" :color="$config['badge']">{{ $config['badgeLabel'] }}</flux:badge>
                            </div>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">{{ $announcement->content }}</p>
                            <span class="mt-1.5 block text-xs text-zinc-400 dark:text-zinc-500">{{ $announcement->published_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->isSuperAdmin)
        <flux:text>{{ __('Selamat datang, Super Admin!') }}</flux:text>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Cafe') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">{{ $this->totalTenants }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Users') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">{{ $this->totalUsers }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Revenue Platform') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">Rp {{ number_format($this->platformRevenue, 0, ',', '.') }}</div>
            </div>
        </div>
    @else
        <flux:text>{{ __('Selamat datang di') }} {{ auth()->user()->tenant?->name ?? 'Dashboard' }}!</flux:text>

        {{-- Subscription Warning --}}
        @if (session('subscription_expired'))
            <flux:callout variant="danger" icon="exclamation-triangle">
                <strong>{{ __('Langganan Anda telah berakhir.') }}</strong>
                {{ __('Hubungi admin untuk memperpanjang langganan agar dapat mengakses fitur operasional.') }}
            </flux:callout>
        @endif

        @php
            $activeSub = auth()->user()->tenant?->activeSubscription;
        @endphp

        @if ($activeSub)
            @if ($activeSub->isExpiringSoon())
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <strong>{{ __('Langganan akan berakhir dalam :days hari.', ['days' => $activeSub->remainingDays()]) }}</strong>
                    {{ __('Hubungi admin untuk memperpanjang langganan.') }}
                </flux:callout>
            @endif

            @if (auth()->user()->isManagement())
                <div class="flex items-center gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                        <flux:icon.credit-card class="size-5 text-indigo-600" />
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium">{{ $activeSub->plan->name }} ({{ $activeSub->plan->durationLabel() }})</div>
                        <div class="text-xs text-zinc-500">
                            {{ __('Berlaku hingga') }} {{ $activeSub->ends_at->format('d M Y') }} &middot;
                            <span class="{{ $activeSub->isExpiringSoon() ? 'font-semibold text-amber-600' : '' }}">{{ $activeSub->remainingDays() }} {{ __('hari tersisa') }}</span>
                        </div>
                    </div>
                    <flux:badge :variant="$activeSub->status->color()" size="sm">{{ $activeSub->status->label() }}</flux:badge>
                </div>
            @endif
        @elseif (auth()->user()->tenant && !$activeSub)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <strong>{{ __('Tidak ada langganan aktif.') }}</strong>
                {{ __('Hubungi admin untuk mengaktifkan langganan.') }}
            </flux:callout>
        @endif

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                        <flux:icon.shopping-cart class="size-5 text-indigo-600" />
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Pesanan Hari Ini') }}</flux:text>
                        <div class="text-2xl font-bold">{{ $this->todayOrders }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                        <flux:icon.banknotes class="size-5 text-emerald-600" />
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Pendapatan Hari Ini') }}</flux:text>
                        <div class="text-2xl font-bold">Rp {{ number_format($this->todayRevenue, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <flux:icon.clock class="size-5 text-amber-600" />
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Order Aktif') }}</flux:text>
                        <div class="text-2xl font-bold">{{ $this->pendingOrders }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $this->lowStockCount > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                        <flux:icon.exclamation-triangle class="size-5 {{ $this->lowStockCount > 0 ? 'text-red-600' : 'text-zinc-400' }}" />
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Stok Rendah') }}</flux:text>
                        <div class="text-2xl font-bold">{{ $this->lowStockCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if (auth()->user()->isManagement())
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Pendapatan 7 Hari Terakhir') }}</flux:heading>
                <div class="flex items-end gap-2" style="height: 200px">
                    @php
                        $maxRevenue = max(array_column($this->weeklyRevenue, 'revenue')) ?: 1;
                    @endphp
                    @foreach ($this->weeklyRevenue as $day)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="w-full rounded-t bg-indigo-500 transition-all" style="height: {{ ($day['revenue'] / $maxRevenue) * 180 }}px; min-height: 4px"></div>
                            <span class="text-xs text-zinc-500">{{ $day['date'] }}</span>
                            <span class="text-xs font-medium">{{ $day['revenue'] > 0 ? 'Rp '.number_format($day['revenue'] / 1000, 0).'k' : '-' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->recentOrders->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Pesanan Terbaru') }}</flux:heading>
                <div class="space-y-3">
                    @foreach ($this->recentOrders as $order)
                        <div class="flex items-center justify-between border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700">
                            <div>
                                <span class="font-mono text-sm font-medium">{{ $order->order_number }}</span>
                                <span class="ml-2 text-sm text-zinc-500">{{ $order->items->count() }} item</span>
                                @if ($order->table)
                                    <span class="ml-2 text-sm text-zinc-500">{{ __('Meja') }} {{ $order->table->number }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:badge :variant="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
                                <span class="font-semibold">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
