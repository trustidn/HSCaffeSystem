<?php

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pelanggan')] class extends Component {
    use WithPagination;

    public string $search = '';

    // Form fields
    public bool $showForm = false;
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|email|max:255')]
    public ?string $email = '';

    #[Validate('nullable|string|max:30|regex:/^[0-9+\-.\s()]*$/')]
    public ?string $phone = '';

    #[Validate('nullable|string|max:500')]
    public ?string $address = '';

    // Detail view
    public bool $showDetail = false;
    public ?int $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function customers()
    {
        return Customer::query()
            ->withCount('orders')
            ->withSum(['orders as total_spending' => fn ($q) => $q->where('payment_status', PaymentStatus::Paid->value)], 'total')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
            ))
            ->latest()
            ->paginate(15);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->email = $customer->email ?? '';
        $this->phone = $customer->phone ?? '';
        $this->address = $customer->address ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
        ];

        if ($this->editingId) {
            Customer::findOrFail($this->editingId)->update($data);
        } else {
            Customer::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
        unset($this->customers);
    }

    public function delete(int $id): void
    {
        Customer::findOrFail($id)->delete();
        unset($this->customers);
    }

    public function showCustomerDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    #[Computed]
    public function detailCustomer(): ?Customer
    {
        if (! $this->detailId) {
            return null;
        }

        return Customer::with(['orders' => fn ($q) => $q->with('items')->latest()->limit(10)])
            ->withCount('orders')
            ->withSum(['orders as total_spending' => fn ($q) => $q->where('payment_status', PaymentStatus::Paid->value)], 'total')
            ->find($this->detailId);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Pelanggan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola data pelanggan/member cafe Anda.') }}</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Tambah Pelanggan') }}</flux:button>
    </div>

    {{-- Search --}}
    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari nama, email, atau telepon...') }}" />

    {{-- Customer List --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Kontak') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Total Order') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Total Belanja') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->customers as $customer)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3">
                            <button wire:click="showCustomerDetail({{ $customer->id }})" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                {{ $customer->name }}
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            @if ($customer->phone)
                                <div class="text-sm">{{ $customer->phone }}</div>
                            @endif
                            @if ($customer->email)
                                <div class="text-xs text-zinc-500">{{ $customer->email }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $customer->orders_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($customer->total_spending ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button wire:click="showCustomerDetail({{ $customer->id }})" variant="ghost" size="sm" icon="eye" />
                                <flux:button wire:click="edit({{ $customer->id }})" variant="ghost" size="sm" icon="pencil" />
                                <flux:button wire:click="delete({{ $customer->id }})" wire:confirm="{{ __('Hapus pelanggan ini?') }}" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                            {{ $search ? __('Tidak ada pelanggan ditemukan.') : __('Belum ada data pelanggan.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->customers->links() }}

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showForm" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit Pelanggan') : __('Tambah Pelanggan') }}</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" label="{{ __('Nama') }}" placeholder="{{ __('Nama pelanggan') }}" required />
                <flux:input wire:model="phone" label="{{ __('Telepon') }}" placeholder="{{ __('08xxxxxxxxxx') }}" />
                <flux:input wire:model="email" type="email" label="{{ __('Email') }}" placeholder="{{ __('email@example.com') }}" />
                <flux:textarea wire:model="address" label="{{ __('Alamat') }}" placeholder="{{ __('Alamat pelanggan') }}" rows="2" />

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="$set('showForm', false)" variant="ghost">{{ __('Batal') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingId ? __('Simpan') : __('Tambah') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Detail Modal --}}
    <flux:modal wire:model="showDetail" class="w-full max-w-2xl">
        @if ($this->detailCustomer)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $this->detailCustomer->name }}</flux:heading>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-zinc-500">
                        @if ($this->detailCustomer->phone)
                            <span class="flex items-center gap-1">
                                <flux:icon.phone class="size-4" /> {{ $this->detailCustomer->phone }}
                            </span>
                        @endif
                        @if ($this->detailCustomer->email)
                            <span class="flex items-center gap-1">
                                <flux:icon.envelope class="size-4" /> {{ $this->detailCustomer->email }}
                            </span>
                        @endif
                    </div>
                    @if ($this->detailCustomer->address)
                        <flux:text class="mt-1 text-sm">{{ $this->detailCustomer->address }}</flux:text>
                    @endif
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="text-xs">{{ __('Total Order') }}</flux:text>
                        <div class="text-2xl font-bold">{{ $this->detailCustomer->orders_count }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="text-xs">{{ __('Total Belanja') }}</flux:text>
                        <div class="text-2xl font-bold text-emerald-600">Rp {{ number_format($this->detailCustomer->total_spending ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Recent Orders --}}
                @if ($this->detailCustomer->orders->isNotEmpty())
                    <div>
                        <flux:heading size="md" class="mb-3">{{ __('Riwayat Pesanan Terbaru') }}</flux:heading>
                        <div class="space-y-2">
                            @foreach ($this->detailCustomer->orders as $order)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-100 p-3 dark:border-zinc-700">
                                    <div>
                                        <span class="font-mono text-sm font-medium">{{ $order->order_number }}</span>
                                        <span class="ml-2 text-xs text-zinc-500">{{ $order->created_at->format('d M Y H:i') }}</span>
                                        <div class="mt-1 text-xs text-zinc-500">{{ $order->items->count() }} item</div>
                                    </div>
                                    <div class="text-right">
                                        <flux:badge :variant="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
                                        <div class="mt-1 font-semibold">Rp {{ number_format($order->total, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <flux:text class="text-center text-zinc-400">{{ __('Belum ada riwayat pesanan.') }}</flux:text>
                @endif

                <div class="flex justify-end">
                    <flux:button wire:click="$set('showDetail', false)" variant="ghost">{{ __('Tutup') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
