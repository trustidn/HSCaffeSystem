<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kitchen Display')] class extends Component {
    public string $filterStatus = 'all';

    #[Computed]
    public function orders()
    {
        $query = Order::query()
            ->with(['items.modifiers', 'table', 'customer'])
            ->whereIn('status', $this->filteredStatuses());

        return $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Order::where('status', OrderStatus::Pending->value)->count();
    }

    public function confirmOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Confirmed->value,
            'confirmed_at' => now(),
        ]);
        unset($this->orders, $this->pendingCount);
    }

    public function startPreparing(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Preparing->value,
            'preparing_at' => now(),
        ]);
        unset($this->orders);
    }

    public function markReady(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Ready->value,
            'ready_at' => now(),
        ]);
        unset($this->orders);
    }

    public function markServed(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Served->value,
            'served_at' => now(),
        ]);
        unset($this->orders);
    }

    public function rejectOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
        unset($this->orders, $this->pendingCount);
    }

    /**
     * @return array<string>
     */
    protected function filteredStatuses(): array
    {
        return match ($this->filterStatus) {
            'pending' => [OrderStatus::Pending->value],
            'active' => [OrderStatus::Confirmed->value, OrderStatus::Preparing->value],
            'ready' => [OrderStatus::Ready->value],
            default => [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ],
        };
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6" wire:poll.5s>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kitchen Display') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pesanan masuk dan yang perlu disiapkan.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:badge as="button" wire:click="$set('filterStatus', 'all')" :variant="$filterStatus === 'all' ? 'primary' : 'default'" size="lg">
                {{ __('Semua') }}
            </flux:badge>
            <flux:badge as="button" wire:click="$set('filterStatus', 'pending')" :variant="$filterStatus === 'pending' ? 'primary' : 'default'" size="lg" class="relative">
                {{ __('Baru') }}
                @if ($this->pendingCount > 0)
                    <span class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                        {{ $this->pendingCount }}
                    </span>
                @endif
            </flux:badge>
            <flux:badge as="button" wire:click="$set('filterStatus', 'active')" :variant="$filterStatus === 'active' ? 'primary' : 'default'" size="lg">
                {{ __('Diproses') }}
            </flux:badge>
            <flux:badge as="button" wire:click="$set('filterStatus', 'ready')" :variant="$filterStatus === 'ready' ? 'primary' : 'default'" size="lg">
                {{ __('Siap Antar') }}
            </flux:badge>
        </div>
    </div>

    {{-- Pending alert banner --}}
    @if ($this->pendingCount > 0 && $filterStatus !== 'pending')
        <div class="flex items-center justify-between rounded-xl border-2 border-red-300 bg-red-50 px-5 py-3 dark:border-red-800 dark:bg-red-950/30">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
                </span>
                <span class="font-medium text-red-700 dark:text-red-300">
                    {{ $this->pendingCount }} {{ __('pesanan baru menunggu konfirmasi') }}
                </span>
            </div>
            <flux:button variant="filled" size="sm" wire:click="$set('filterStatus', 'pending')" class="!bg-red-600 !text-white hover:!bg-red-700">
                {{ __('Lihat') }}
            </flux:button>
        </div>
    @endif

    @if ($this->orders->isEmpty())
        <div class="flex h-64 items-center justify-center text-zinc-400">
            <div class="text-center">
                <flux:icon.check-circle class="mx-auto size-16" />
                <flux:text size="lg" class="mt-3">{{ __('Tidak ada pesanan saat ini.') }}</flux:text>
            </div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->orders as $order)
            <div wire:key="kds-{{ $order->id }}" class="rounded-xl border-2 p-4 space-y-3
                {{ $order->status === \App\Enums\OrderStatus::Pending ? 'border-red-400 bg-red-50 dark:bg-red-950/20 animate-pulse-once' : '' }}
                {{ $order->status === \App\Enums\OrderStatus::Confirmed ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/20' : '' }}
                {{ $order->status === \App\Enums\OrderStatus::Preparing ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-950/20' : '' }}
                {{ $order->status === \App\Enums\OrderStatus::Ready ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/20' : '' }}
            ">
                {{-- Header --}}
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            @if ($order->status === \App\Enums\OrderStatus::Pending)
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                </span>
                            @endif
                            <span class="font-mono text-sm font-bold">{{ $order->order_number }}</span>
                        </div>
                        <div class="text-xs text-zinc-500">
                            {{ $order->type->label() }}
                            @if ($order->table) - {{ __('Meja') }} {{ $order->table->number }} @endif
                        </div>
                        @if ($order->customer)
                            <div class="text-xs text-zinc-500">{{ $order->customer->name }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <flux:badge :variant="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
                        <div class="mt-1">
                            <flux:badge :variant="$order->payment_status->color()" size="sm">{{ $order->payment_status->label() }}</flux:badge>
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">{{ $order->created_at->diffForHumans() }}</div>
                    </div>
                </div>

                {{-- Items --}}
                <div class="space-y-2">
                    @foreach ($order->items as $item)
                        <div wire:key="kds-item-{{ $item->id }}" class="rounded-lg bg-white p-2 dark:bg-zinc-800">
                            <div class="flex items-center justify-between">
                                <div class="font-medium">
                                    <span class="mr-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-200 text-xs font-bold dark:bg-zinc-700">{{ $item->quantity }}</span>
                                    {{ $item->item_name }}
                                    @if ($item->variant_name)
                                        <span class="text-sm text-zinc-500">({{ $item->variant_name }})</span>
                                    @endif
                                </div>
                            </div>
                            @foreach ($item->modifiers as $mod)
                                <div class="ml-8 text-xs text-zinc-500">+ {{ $mod->modifier_name }}</div>
                            @endforeach
                            @if ($item->notes)
                                <div class="ml-8 text-xs italic text-amber-600 dark:text-amber-400">{{ $item->notes }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($order->notes)
                    <div class="rounded bg-amber-100 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        {{ $order->notes }}
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex gap-2">
                    @if ($order->status === \App\Enums\OrderStatus::Pending)
                        <flux:button variant="primary" class="flex-1" wire:click="confirmOrder({{ $order->id }})">
                            {{ __('Konfirmasi') }}
                        </flux:button>
                        <flux:button variant="danger" wire:click="rejectOrder({{ $order->id }})" wire:confirm="{{ __('Tolak pesanan ini?') }}">
                            {{ __('Tolak') }}
                        </flux:button>
                    @elseif ($order->status === \App\Enums\OrderStatus::Confirmed)
                        <flux:button variant="primary" class="flex-1" wire:click="startPreparing({{ $order->id }})">
                            {{ __('Mulai Proses') }}
                        </flux:button>
                    @elseif ($order->status === \App\Enums\OrderStatus::Preparing)
                        <flux:button variant="primary" class="flex-1" wire:click="markReady({{ $order->id }})">
                            {{ __('Siap!') }}
                        </flux:button>
                    @elseif ($order->status === \App\Enums\OrderStatus::Ready)
                        <flux:button variant="primary" class="flex-1" wire:click="markServed({{ $order->id }})">
                            {{ __('Sudah Diantar') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
