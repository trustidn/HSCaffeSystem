<?php

use App\Models\Tenant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Cafe')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $name = '';
    public string $slug = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $primaryColor = '#6366f1';
    public string $secondaryColor = '#818cf8';
    public string $taxRate = '11.00';
    public string $serviceChargeRate = '0.00';

    public ?int $editingTenantId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::query()
            ->when($this->search, fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
            )
            ->withCount('users')
            ->with('activeSubscription.plan')
            ->latest()
            ->paginate(10);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $this->editingTenantId = $tenant->id;
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->email = $tenant->email ?? '';
        $this->phone = $tenant->phone ?? '';
        $this->address = $tenant->address ?? '';
        $this->primaryColor = $tenant->primary_color;
        $this->secondaryColor = $tenant->secondary_color;
        $this->taxRate = (string) $tenant->tax_rate;
        $this->serviceChargeRate = (string) $tenant->service_charge_rate;
        $this->showEditModal = true;
    }

    public function createTenant(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'regex:/^[a-z0-9-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'primaryColor' => ['required', 'string', 'max:7'],
            'secondaryColor' => ['required', 'string', 'max:7'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'serviceChargeRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'primary_color' => $validated['primaryColor'],
            'secondary_color' => $validated['secondaryColor'],
            'tax_rate' => $validated['taxRate'],
            'service_charge_rate' => $validated['serviceChargeRate'],
        ]);

        \App\Models\AuditLog::record('tenant_create', 'Cafe dibuat: ' . $validated['name'], ['tenant_id' => $tenant->id]);

        $this->showCreateModal = false;
        $this->resetForm();
        unset($this->tenants);

        session()->flash('success', 'Cafe berhasil ditambahkan.');
    }

    public function updateTenant(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug,'.$this->editingTenantId, 'regex:/^[a-z0-9-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'primaryColor' => ['required', 'string', 'max:7'],
            'secondaryColor' => ['required', 'string', 'max:7'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'serviceChargeRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenant = Tenant::findOrFail($this->editingTenantId);
        $tenant->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'primary_color' => $validated['primaryColor'],
            'secondary_color' => $validated['secondaryColor'],
            'tax_rate' => $validated['taxRate'],
            'service_charge_rate' => $validated['serviceChargeRate'],
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        unset($this->tenants);

        session()->flash('success', 'Cafe berhasil diperbarui.');
    }

    public function toggleActive(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->update(['is_active' => ! $tenant->is_active]);
        unset($this->tenants);
    }

    public function confirmDelete(int $tenantId): void
    {
        $this->editingTenantId = $tenantId;
        $this->showDeleteModal = true;
    }

    public function deleteTenant(): void
    {
        $tenant = Tenant::findOrFail($this->editingTenantId);

        \App\Models\AuditLog::record('tenant_delete', 'Cafe dihapus: ' . $tenant->name, ['tenant_id' => $tenant->id]);

        $tenant->delete();

        $this->showDeleteModal = false;
        $this->editingTenantId = null;
        unset($this->tenants);

        session()->flash('success', 'Cafe berhasil dihapus.');
    }

    public function updatedName(): void
    {
        if (! $this->editingTenantId) {
            $this->slug = \Illuminate\Support\Str::slug($this->name);
        }
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->slug = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->primaryColor = '#6366f1';
        $this->secondaryColor = '#818cf8';
        $this->taxRate = '11.00';
        $this->serviceChargeRate = '0.00';
        $this->editingTenantId = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kelola Cafe') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola semua cafe yang terdaftar di platform.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Tambah Cafe') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">
            {{ session('success') }}
        </flux:callout>
    @endif

    <div class="flex items-center gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari cafe...')" />
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Cafe') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Staff') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Langganan') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->tenants as $tenant)
                    <tr wire:key="tenant-{{ $tenant->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg text-white text-sm font-bold" style="background-color: {{ $tenant->primary_color }}">
                                    {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-medium">{{ $tenant->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $tenant->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $tenant->email ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm">{{ $tenant->users_count }} {{ __('user') }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($tenant->activeSubscription)
                                <div>
                                    <flux:badge :variant="$tenant->activeSubscription->status->color()" size="sm">
                                        {{ $tenant->activeSubscription->plan->name }}
                                    </flux:badge>
                                    <div class="mt-0.5 text-xs text-zinc-500">
                                        {{ $tenant->activeSubscription->remainingDays() }} {{ __('hari tersisa') }}
                                    </div>
                                </div>
                            @else
                                <flux:badge variant="red" size="sm">{{ __('Tidak Aktif') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:switch wire:click="toggleActive({{ $tenant->id }})" :checked="$tenant->is_active" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="credit-card" :href="route('admin.tenant.subscription', $tenant)" wire:navigate />
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $tenant->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $tenant->id }})" class="text-red-500 hover:text-red-700" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                            {{ __('Belum ada cafe yang terdaftar.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $this->tenants->links() }}
    </div>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="createTenant" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah Cafe Baru') }}</flux:heading>

            <flux:input wire:model.live.debounce.300ms="name" :label="__('Nama Cafe')" required />
            <flux:input wire:model="slug" :label="__('Slug (URL)')" required :description="__('Hanya huruf kecil, angka, dan tanda hubung.')" />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
            <flux:input wire:model="phone" :label="__('Telepon')" />
            <flux:textarea wire:model="address" :label="__('Alamat')" rows="2" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="primaryColor" :label="__('Warna Primer')" type="color" />
                <flux:input wire:model="secondaryColor" :label="__('Warna Sekunder')" type="color" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="taxRate" :label="__('Pajak (%)')" type="number" step="0.01" />
                <flux:input wire:model="serviceChargeRate" :label="__('Service Charge (%)')" type="number" step="0.01" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-lg">
        <form wire:submit="updateTenant" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Cafe') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Nama Cafe')" required />
            <flux:input wire:model="slug" :label="__('Slug (URL)')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
            <flux:input wire:model="phone" :label="__('Telepon')" />
            <flux:textarea wire:model="address" :label="__('Alamat')" rows="2" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="primaryColor" :label="__('Warna Primer')" type="color" />
                <flux:input wire:model="secondaryColor" :label="__('Warna Sekunder')" type="color" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="taxRate" :label="__('Pajak (%)')" type="number" step="0.01" />
                <flux:input wire:model="serviceChargeRate" :label="__('Service Charge (%)')" type="number" step="0.01" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Perbarui') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Cafe') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin ingin menghapus cafe ini? Semua data terkait akan ikut terhapus.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteTenant">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
