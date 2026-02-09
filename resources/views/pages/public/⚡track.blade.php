<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Tenant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Lacak Pesanan')] #[Layout('layouts.public')] class extends Component {
    public ?Tenant $tenant = null;

    #[Validate('required|string|min:3')]
    public string $searchQuery = '';

    public bool $searched = false;

    /** @var \Illuminate\Support\Collection<int, Order>|null */
    public $orders = null;

    public function mount(string $tenantSlug): void
    {
        $this->tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->firstOrFail();

        if ($q = request()->query('q')) {
            $this->searchQuery = $q;
            $this->search();
        }
    }

    public function search(): void
    {
        $this->validate();
        $this->searched = true;

        $this->refreshOrders();
    }

    /**
     * Refresh order data (called by search and polling).
     */
    public function refreshOrders(): void
    {
        if (! $this->searched || empty($this->searchQuery)) {
            return;
        }

        $this->orders = Order::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->with(['items', 'table'])
            ->where(fn ($q) => $q
                ->where('order_number', 'like', "%{$this->searchQuery}%")
                ->orWhereHas('customer', fn ($cq) => $cq->where('phone', 'like', "%{$this->searchQuery}%"))
            )
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * @return array<int, array{status: string, label: string, active: bool, done: bool}>
     */
    public function getStatusSteps(Order $order): array
    {
        $statuses = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Served,
            OrderStatus::Completed,
        ];

        if ($order->status === OrderStatus::Cancelled) {
            return [['status' => 'cancelled', 'label' => 'Dibatalkan', 'active' => true, 'done' => false]];
        }

        $currentIndex = array_search($order->status, $statuses);
        $steps = [];

        foreach ($statuses as $i => $status) {
            $steps[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'active' => $i === $currentIndex,
                'done' => $i < $currentIndex,
            ];
        }

        return $steps;
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900"
     @if ($searched && $orders && $orders->contains(fn ($o) => ! in_array($o->status->value, ['completed', 'cancelled'])))
         wire:poll.5s="refreshOrders"
     @endif
>
    {{-- Header --}}
    <div class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800"
         @if ($tenant?->primary_color) style="border-bottom-color: {{ $tenant->primary_color }}" @endif>
        <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-4">
            <div>
                <h1 class="text-lg font-bold" @if ($tenant?->primary_color) style="color: {{ $tenant->primary_color }}" @endif>
                    {{ $tenant?->name ?? 'Cafe' }}
                </h1>
                <p class="text-sm text-zinc-500">{{ __('Lacak Status Pesanan') }}</p>
            </div>
            <a href="{{ route('public.order', $tenant->slug) }}" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                {{ __('Pesan Lagi') }}
            </a>
        </div>
    </div>

    <div class="mx-auto max-w-2xl space-y-6 px-4 py-6">
        {{-- Search Form --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Cari Pesanan') }}</h2>
            <form wire:submit="search" class="flex gap-3">
                <div class="flex-1">
                    <input wire:model="searchQuery"
                           type="text"
                           class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700"
                           placeholder="{{ __('Nomor order atau nomor telepon...') }}" />
                    @error('searchQuery')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="rounded-lg px-6 py-2.5 text-sm font-medium text-white"
                        style="background-color: {{ $tenant?->primary_color ?? '#6366f1' }}"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="search">{{ __('Cari') }}</span>
                    <span wire:loading wire:target="search">{{ __('...') }}</span>
                </button>
            </form>
        </div>

        {{-- Live indicator --}}
        @if ($searched && $orders && $orders->contains(fn ($o) => ! in_array($o->status->value, ['completed', 'cancelled'])))
            <div class="flex items-center justify-center gap-2 text-sm text-zinc-500">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                {{ __('Status diperbarui otomatis') }}
            </div>
        @endif

        {{-- Results --}}
        @if ($searched)
            @if ($orders && $orders->isNotEmpty())
                @foreach ($orders as $order)
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                        {{-- Order Header --}}
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-mono text-lg font-bold">{{ $order->order_number }}</span>
                                <div class="mt-1 text-sm text-zinc-500">{{ $order->created_at->format('d M Y, H:i') }}</div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-medium"
                                  style="background-color: {{ $tenant?->primary_color ?? '#6366f1' }}20; color: {{ $tenant?->primary_color ?? '#6366f1' }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>

                        {{-- Status Steps --}}
                        @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                            <div class="my-6">
                                <div class="flex items-center justify-between">
                                    @foreach ($this->getStatusSteps($order) as $i => $step)
                                        <div class="flex flex-1 flex-col items-center text-center">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold
                                                {{ $step['done'] ? 'text-white' : ($step['active'] ? 'text-white animate-pulse' : 'bg-zinc-200 text-zinc-400 dark:bg-zinc-700') }}"
                                                style="{{ $step['done'] || $step['active'] ? 'background-color: '.($tenant?->primary_color ?? '#6366f1') : '' }}">
                                                @if ($step['done'])
                                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                @else
                                                    {{ $i + 1 }}
                                                @endif
                                            </div>
                                            <span class="mt-1 text-[10px] {{ $step['active'] ? 'font-bold' : 'text-zinc-400' }}">{{ $step['label'] }}</span>
                                        </div>
                                        @if (! $loop->last)
                                            <div class="mx-1 mt-[-16px] h-0.5 flex-1 {{ $step['done'] ? '' : 'bg-zinc-200 dark:bg-zinc-700' }}"
                                                 style="{{ $step['done'] ? 'background-color: '.($tenant?->primary_color ?? '#6366f1') : '' }}"></div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="my-4 rounded-lg bg-red-50 p-3 text-center text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                {{ __('Pesanan ini telah dibatalkan.') }}
                            </div>
                        @endif

                        {{-- Order Items --}}
                        <div class="space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-700">
                            @foreach ($order->items as $item)
                                <div class="flex items-center justify-between text-sm">
                                    <div>
                                        <span>{{ $item->quantity }}x</span>
                                        <span class="ml-1 font-medium">{{ $item->item_name }}</span>
                                        @if ($item->variant_name)
                                            <span class="text-zinc-400">({{ $item->variant_name }})</span>
                                        @endif
                                    </div>
                                    <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Total --}}
                        <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-700">
                            <span class="font-semibold">{{ __('Total') }}</span>
                            <span class="text-lg font-bold" style="color: {{ $tenant?->primary_color ?? '#6366f1' }}">
                                Rp {{ number_format($order->total, 0, ',', '.') }}
                            </span>
                        </div>

                        @if ($order->table)
                            <div class="mt-2 text-sm text-zinc-500">{{ __('Meja') }}: {{ $order->table->number }}</div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="text-zinc-400">
                        <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <p class="mt-4 text-zinc-500">{{ __('Pesanan tidak ditemukan.') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">{{ __('Pastikan nomor order atau nomor telepon benar.') }}</p>
                </div>
            @endif
        @endif
    </div>
</div>
