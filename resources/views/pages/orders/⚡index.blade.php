<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan')] class extends Component {
    use WithPagination;

    public string $filterStatus = '';

    public string $filterPayment = '';

    public string $filterTable = '';

    public string $search = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public bool $showDetailModal = false;
    public bool $showResetModal = false;
    public string $resetConfirmation = '';
    public ?int $selectedOrderId = null;

    public int $lastPendingCount = 0;

    public function mount(): void
    {
        $this->lastPendingCount = Order::where('status', OrderStatus::Pending->value)->count();
    }

    public function checkNewOrders(): void
    {
        $current = Order::where('status', OrderStatus::Pending->value)->count();
        if ($current > $this->lastPendingCount) {
            $this->lastPendingCount = $current;
        }
        unset($this->orders);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTable(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tables(): \Illuminate\Database\Eloquent\Collection
    {
        return Table::orderBy('number')->get();
    }

    public function sort(string $column): void
    {
        $allowed = ['order_number', 'type', 'total', 'status', 'payment_status', 'created_at'];

        if (! in_array($column, $allowed)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        unset($this->orders);
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->with(['table', 'customer', 'cashier', 'items'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPayment, fn ($q) => $q->where('payment_status', $this->filterPayment))
            ->when($this->filterTable !== '', function ($q) {
                if ($this->filterTable === 'none') {
                    $q->whereNull('table_id');
                } else {
                    $q->where('table_id', $this->filterTable);
                }
            })
            ->when($this->search, fn ($q) => $q->where('order_number', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->orderBy('id', $this->sortDirection)
            ->paginate(20);
    }

    #[Computed]
    public function selectedOrder()
    {
        if (! $this->selectedOrderId) {
            return null;
        }

        return Order::with(['items.modifiers', 'table', 'customer', 'cashier'])
            ->find($this->selectedOrderId);
    }

    public function showDetail(int $id): void
    {
        $this->selectedOrderId = $id;
        unset($this->selectedOrder);
        $this->showDetailModal = true;
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $order = Order::findOrFail($orderId);
        $timestamps = match ($status) {
            'confirmed' => ['confirmed_at' => now()],
            'preparing' => ['preparing_at' => now()],
            'ready' => ['ready_at' => now()],
            'served' => ['served_at' => now()],
            'completed' => ['completed_at' => now()],
            'cancelled' => ['cancelled_at' => now()],
            default => [],
        };

        $order->update(array_merge(['status' => $status], $timestamps));
        unset($this->orders, $this->selectedOrder);
    }

    public function markAsPaid(int $orderId): void
    {
        Order::findOrFail($orderId)->update(['payment_status' => PaymentStatus::Paid->value]);
        unset($this->orders, $this->selectedOrder);
    }

    public function openResetModal(): void
    {
        $this->resetConfirmation = '';
        $this->showResetModal = true;
    }

    public function resetAllTransactions(): void
    {
        if ($this->resetConfirmation !== 'HAPUS SEMUA') {
            $this->addError('resetConfirmation', __('Ketik "HAPUS SEMUA" untuk konfirmasi.'));

            return;
        }

        if (! auth()->user()->isOwner()) {
            abort(403);
        }

        $tenant = auth()->user()->tenant;

        // Delete payments, order item modifiers, order items, then orders
        $orderIds = Order::pluck('id');
        \App\Models\Payment::whereIn('order_id', $orderIds)->delete();

        $orderItemIds = \App\Models\OrderItem::whereIn('order_id', $orderIds)->pluck('id');
        \App\Models\OrderItemModifier::whereIn('order_item_id', $orderItemIds)->delete();
        \App\Models\OrderItem::whereIn('order_id', $orderIds)->delete();

        Order::query()->delete();

        // Reset table statuses back to available
        \App\Models\Table::where('status', 'occupied')->update(['status' => 'available']);

        $this->showResetModal = false;
        $this->resetConfirmation = '';
        unset($this->orders);

        session()->flash('success', __('Semua transaksi berhasil dihapus.'));
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6" wire:poll.3s="checkNewOrders">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Pesanan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola semua pesanan masuk.') }}</flux:text>
        </div>
        @if (auth()->user()->isOwner())
            <flux:button variant="danger" size="sm" icon="trash" wire:click="openResetModal">
                {{ __('Reset Transaksi') }}
            </flux:button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari nomor order...')" />
        </div>
        <flux:select wire:model.live="filterStatus" class="w-40">
            <option value="">{{ __('Semua Status') }}</option>
            @foreach (OrderStatus::cases() as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterPayment" class="w-40">
            <option value="">{{ __('Semua Bayar') }}</option>
            @foreach (PaymentStatus::cases() as $ps)
                <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterTable" class="w-40">
            <option value="">{{ __('Semua Meja') }}</option>
            <option value="none">{{ __('Tanpa meja') }}</option>
            @foreach ($this->tables as $table)
                <option value="{{ $table->id }}">{{ __('Meja') }} {{ $table->number }}</option>
            @endforeach
        </flux:select>
    </div>

    {{-- Orders Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    @php
                        $sortIcon = fn(string $col) => $sortBy === $col
                            ? ($sortDirection === 'asc' ? '↑' : '↓')
                            : '';
                    @endphp
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('order_number')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('No. Order') }} <span class="text-xs text-indigo-500">{{ $sortIcon('order_number') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('type')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Tipe') }} <span class="text-xs text-indigo-500">{{ $sortIcon('type') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">{{ __('Meja') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Items') }}</th>
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('total')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Total') }} <span class="text-xs text-indigo-500">{{ $sortIcon('total') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('status')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Status') }} <span class="text-xs text-indigo-500">{{ $sortIcon('status') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('payment_status')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Bayar') }} <span class="text-xs text-indigo-500">{{ $sortIcon('payment_status') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Waktu') }} <span class="text-xs text-indigo-500">{{ $sortIcon('created_at') }}</span>
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->orders as $order)
                    <tr wire:key="order-{{ $order->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-xs font-medium">{{ $order->order_number }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $order->type->label() }}</flux:badge></td>
                        <td class="px-4 py-3">{{ $order->table ? __('Meja').' '.$order->table->number : '-' }}</td>
                        <td class="px-4 py-3">{{ $order->items->count() }} item</td>
                        <td class="px-4 py-3 font-semibold">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3"><flux:badge :variant="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge></td>
                        <td class="px-4 py-3"><flux:badge :variant="$order->payment_status->color()" size="sm">{{ $order->payment_status->label() }}</flux:badge></td>
                        <td class="px-4 py-3 text-xs text-zinc-500">
                            <div>{{ $order->created_at->format('d/m/Y') }}</div>
                            <div>{{ $order->created_at->format('H:i') }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($order->status === \App\Enums\OrderStatus::Pending)
                                    <flux:button variant="primary" size="sm" wire:click="updateOrderStatus({{ $order->id }}, 'confirmed')">{{ __('Konfirmasi') }}</flux:button>
                                @endif
                                <flux:button variant="ghost" size="sm" icon="eye" wire:click="showDetail({{ $order->id }})" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada pesanan.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $this->orders->links() }}</div>

    {{-- Order Detail Modal --}}
    <flux:modal wire:model="showDetailModal" class="max-w-lg">
        @if ($this->selectedOrder)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $this->selectedOrder->order_number }}</flux:heading>
                    <flux:badge :variant="$this->selectedOrder->status->color()">{{ $this->selectedOrder->status->label() }}</flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="text-zinc-500">{{ __('Tipe:') }}</span> {{ $this->selectedOrder->type->label() }}</div>
                    <div><span class="text-zinc-500">{{ __('Meja:') }}</span> {{ $this->selectedOrder->table ? __('Meja').' '.$this->selectedOrder->table->number : '-' }}</div>
                    <div><span class="text-zinc-500">{{ __('Customer:') }}</span> {{ $this->selectedOrder->customer?->name ?? '-' }}</div>
                    <div><span class="text-zinc-500">{{ __('Kasir:') }}</span> {{ $this->selectedOrder->cashier?->name ?? '-' }}</div>
                </div>

                {{-- Items --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    @foreach ($this->selectedOrder->items as $item)
                        <div wire:key="detail-item-{{ $item->id }}" class="flex items-center justify-between border-b border-zinc-200 px-4 py-2 last:border-b-0 dark:border-zinc-700">
                            <div>
                                <div class="font-medium">{{ $item->item_name }} @if ($item->variant_name) <span class="text-xs text-zinc-500">({{ $item->variant_name }})</span> @endif</div>
                                @foreach ($item->modifiers as $mod)
                                    <div class="text-xs text-zinc-500">+ {{ $mod->modifier_name }}</div>
                                @endforeach
                                @if ($item->notes)
                                    <div class="text-xs italic text-zinc-400">{{ $item->notes }}</div>
                                @endif
                            </div>
                            <div class="text-right text-sm">
                                <div>{{ $item->quantity }}x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</div>
                                <div class="font-semibold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Totals --}}
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>Rp {{ number_format($this->selectedOrder->subtotal, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between text-zinc-500"><span>Pajak</span><span>Rp {{ number_format($this->selectedOrder->tax_amount, 0, ',', '.') }}</span></div>
                    @if ($this->selectedOrder->service_charge > 0)
                        <div class="flex justify-between text-zinc-500"><span>Service</span><span>Rp {{ number_format($this->selectedOrder->service_charge, 0, ',', '.') }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-1 text-lg font-bold dark:border-zinc-600"><span>Total</span><span>Rp {{ number_format($this->selectedOrder->total, 0, ',', '.') }}</span></div>
                </div>

                {{-- Cetak --}}
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:text size="sm" class="mb-2 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Cetak') }}</flux:text>
                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="route('orders.print.waiter', $this->selectedOrder->id) . '?auto=1'" target="_blank" rel="noopener" variant="ghost" size="sm" icon="document-text">
                            {{ __('Waitres') }}
                        </flux:button>
                        <flux:button :href="route('orders.print.kitchen', $this->selectedOrder->id) . '?auto=1'" target="_blank" rel="noopener" variant="ghost" size="sm" icon="fire">
                            {{ __('Dapur') }}
                        </flux:button>
                        <flux:button :href="route('orders.print.receipt', $this->selectedOrder->id) . '?auto=1'" target="_blank" rel="noopener" variant="ghost" size="sm" icon="banknotes">
                            {{ __('Struk') }}
                        </flux:button>
                    </div>
                </div>

                {{-- Status Actions --}}
                <div class="flex flex-wrap gap-2">
                    @if ($this->selectedOrder->status === \App\Enums\OrderStatus::Pending)
                        <flux:button variant="primary" size="sm" wire:click="updateOrderStatus({{ $this->selectedOrder->id }}, 'confirmed')">{{ __('Konfirmasi') }}</flux:button>
                        <flux:button variant="danger" size="sm" wire:click="updateOrderStatus({{ $this->selectedOrder->id }}, 'cancelled')">{{ __('Batalkan') }}</flux:button>
                    @endif
                    @if ($this->selectedOrder->status === \App\Enums\OrderStatus::Ready)
                        <flux:button variant="primary" size="sm" wire:click="updateOrderStatus({{ $this->selectedOrder->id }}, 'served')">{{ __('Antar ke Meja') }}</flux:button>
                    @endif
                    @if ($this->selectedOrder->status === \App\Enums\OrderStatus::Served)
                        <flux:button variant="primary" size="sm" wire:click="updateOrderStatus({{ $this->selectedOrder->id }}, 'completed')">{{ __('Selesai') }}</flux:button>
                    @endif
                    @if ($this->selectedOrder->payment_status === \App\Enums\PaymentStatus::Unpaid)
                        <flux:button size="sm" wire:click="markAsPaid({{ $this->selectedOrder->id }})">{{ __('Tandai Lunas') }}</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Reset All Transactions Modal --}}
    <flux:modal wire:model="showResetModal" class="max-w-sm">
        <div class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-7 text-red-600" />
            </div>

            <div class="text-center">
                <flux:heading size="lg">{{ __('Reset Semua Transaksi') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Tindakan ini akan menghapus SEMUA pesanan, pembayaran, dan item pesanan secara permanen. Data tidak dapat dikembalikan.') }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="mb-1">{{ __('Ketik "HAPUS SEMUA" untuk konfirmasi:') }}</flux:text>
                <flux:input wire:model.live="resetConfirmation" placeholder="HAPUS SEMUA" />
                @error('resetConfirmation')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2">
                <flux:button wire:click="$set('showResetModal', false)" class="flex-1">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="resetAllTransactions" class="flex-1" :disabled="$resetConfirmation !== 'HAPUS SEMUA'">
                    {{ __('Hapus Semua') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
