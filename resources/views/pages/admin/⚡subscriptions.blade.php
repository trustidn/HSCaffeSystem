<?php

use App\Models\SubscriptionPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Paket Langganan')] class extends Component {
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingPlanId = null;
    public string $name = '';
    public int $durationMonths = 1;
    public string $price = '';
    public string $description = '';
    public string $features = '';
    public bool $isActive = true;
    public int $sortOrder = 0;

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('duration_months')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->name = $plan->name;
        $this->durationMonths = $plan->duration_months;
        $this->price = (string) $plan->price;
        $this->description = $plan->description ?? '';
        $this->features = $plan->features ? implode("\n", $plan->features) : '';
        $this->isActive = $plan->is_active;
        $this->sortOrder = $plan->sort_order;
        $this->showFormModal = true;
    }

    public function savePlan(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'durationMonths' => ['required', 'integer', 'in:1,3,6,12'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'features' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'sortOrder' => ['integer', 'min:0'],
        ]);

        $featuresArray = $validated['features']
            ? array_filter(array_map('trim', explode("\n", $validated['features'])))
            : null;

        $data = [
            'name' => $validated['name'],
            'duration_months' => $validated['durationMonths'],
            'price' => $validated['price'],
            'description' => $validated['description'] ?: null,
            'features' => $featuresArray,
            'is_active' => $validated['isActive'],
            'sort_order' => $validated['sortOrder'],
        ];

        if ($this->editingPlanId) {
            SubscriptionPlan::findOrFail($this->editingPlanId)->update($data);
            session()->flash('success', __('Paket berhasil diperbarui.'));
        } else {
            SubscriptionPlan::create($data);
            session()->flash('success', __('Paket berhasil ditambahkan.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->plans);
    }

    public function toggleActive(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
        unset($this->plans);
    }

    public function confirmDelete(int $id): void
    {
        $this->editingPlanId = $id;
        $this->showDeleteModal = true;
    }

    public function deletePlan(): void
    {
        $plan = SubscriptionPlan::findOrFail($this->editingPlanId);

        if ($plan->subscriptions()->exists()) {
            session()->flash('error', __('Paket ini masih memiliki langganan aktif dan tidak dapat dihapus.'));
        } else {
            $plan->delete();
            session()->flash('success', __('Paket berhasil dihapus.'));
        }

        $this->showDeleteModal = false;
        $this->editingPlanId = null;
        unset($this->plans);
    }

    protected function resetForm(): void
    {
        $this->editingPlanId = null;
        $this->name = '';
        $this->durationMonths = 1;
        $this->price = '';
        $this->description = '';
        $this->features = '';
        $this->isActive = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Paket Langganan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola paket langganan yang tersedia untuk cafe.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Tambah Paket') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    {{-- Plans Grid --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($this->plans as $plan)
            <div wire:key="plan-{{ $plan->id }}" class="relative flex flex-col rounded-xl border {{ $plan->is_active ? 'border-zinc-200 dark:border-zinc-700' : 'border-zinc-200/50 opacity-60 dark:border-zinc-700/50' }} p-5">
                @unless ($plan->is_active)
                    <div class="absolute right-3 top-3">
                        <flux:badge variant="red" size="sm">{{ __('Nonaktif') }}</flux:badge>
                    </div>
                @endunless

                <div class="mb-4">
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    <flux:badge variant="sky" size="sm" class="mt-1">{{ $plan->durationLabel() }}</flux:badge>
                </div>

                <div class="mb-4">
                    <div class="text-2xl font-bold">{{ $plan->formattedPrice() }}</div>
                    @if ($plan->duration_months > 1)
                        <flux:text size="sm">
                            {{ 'Rp ' . number_format($plan->pricePerMonth(), 0, ',', '.') }} {{ __('/ bulan') }}
                        </flux:text>
                    @endif
                </div>

                @if ($plan->description)
                    <flux:text size="sm" class="mb-3">{{ $plan->description }}</flux:text>
                @endif

                @if ($plan->features)
                    <ul class="mb-4 space-y-1.5 text-sm">
                        @foreach ($plan->features as $feature)
                            <li class="flex items-start gap-2">
                                <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-auto flex items-center gap-1 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:text size="xs" class="mr-auto">
                        {{ $plan->subscriptions_count }} {{ __('subscriber') }}
                    </flux:text>
                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $plan->id }})" />
                    <flux:button variant="ghost" size="sm" :icon="$plan->is_active ? 'eye-slash' : 'eye'" wire:click="toggleActive({{ $plan->id }})" />
                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $plan->id }})" class="text-red-500" />
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-zinc-500">
                {{ __('Belum ada paket langganan. Tambahkan paket baru.') }}
            </div>
        @endforelse
    </div>

    {{-- Form Modal --}}
    <flux:modal wire:model="showFormModal" class="max-w-lg">
        <form wire:submit="savePlan" class="space-y-4">
            <flux:heading size="lg">
                {{ $editingPlanId ? __('Edit Paket') : __('Tambah Paket Baru') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nama Paket')" required :placeholder="__('contoh: Paket Bulanan')" />

            <flux:select wire:model="durationMonths" :label="__('Durasi')">
                <option value="1">{{ __('1 Bulan') }}</option>
                <option value="3">{{ __('3 Bulan') }}</option>
                <option value="6">{{ __('6 Bulan') }}</option>
                <option value="12">{{ __('1 Tahun') }}</option>
            </flux:select>

            <flux:input wire:model="price" :label="__('Harga (Rp)')" type="number" min="0" step="1000" required :placeholder="__('contoh: 199000')" />

            <flux:textarea wire:model="description" :label="__('Deskripsi')" rows="2" :placeholder="__('Deskripsi singkat paket ini')" />

            <flux:textarea wire:model="features" :label="__('Fitur (satu per baris)')" rows="4" :placeholder="__('Menu Management\nPOS / Kasir\nKitchen Display\nLaporan')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="sortOrder" :label="__('Urutan')" type="number" min="0" />
                <div class="flex items-end pb-1">
                    <flux:switch wire:model="isActive" :label="__('Aktif')" />
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showFormModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $editingPlanId ? __('Perbarui') : __('Simpan') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Paket') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin ingin menghapus paket ini?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deletePlan">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
