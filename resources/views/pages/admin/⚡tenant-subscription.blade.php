<?php

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Langganan Cafe')] class extends Component {
    public Tenant $tenant;

    public bool $showAddModal = false;
    public bool $showEditModal = false;

    public ?int $editingSubscriptionId = null;
    public ?int $selectedPlanId = null;
    public string $startsAt = '';
    public string $pricePaid = '';
    public string $paymentReference = '';
    public string $notes = '';
    public string $status = 'active';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    #[Computed]
    public function subscriptions()
    {
        return Subscription::where('tenant_id', $this->tenant->id)
            ->with('plan')
            ->latest('starts_at')
            ->get();
    }

    #[Computed]
    public function activePlans()
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('duration_months')
            ->get();
    }

    #[Computed]
    public function activeSubscription()
    {
        return $this->tenant->activeSubscription;
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        $this->startsAt = now()->format('Y-m-d');
        $this->showAddModal = true;
    }

    public function updatedSelectedPlanId(): void
    {
        if ($this->selectedPlanId) {
            $plan = SubscriptionPlan::find($this->selectedPlanId);
            if ($plan) {
                $this->pricePaid = (string) $plan->price;
            }
        }
    }

    public function addSubscription(): void
    {
        $this->validate([
            'selectedPlanId' => ['required', 'exists:subscription_plans,id'],
            'startsAt' => ['required', 'date'],
            'pricePaid' => ['required', 'numeric', 'min:0'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = SubscriptionPlan::findOrFail($this->selectedPlanId);
        $startsAt = \Carbon\Carbon::parse($this->startsAt);
        $endsAt = $startsAt->copy()->addMonths($plan->duration_months);

        // Expire any current active subscription
        Subscription::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trial->value])
            ->where('ends_at', '>=', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'price_paid' => $this->pricePaid,
            'status' => SubscriptionStatus::Active,
            'payment_reference' => $this->paymentReference ?: null,
            'notes' => $this->notes ?: null,
        ]);

        $this->showAddModal = false;
        $this->resetForm();
        unset($this->subscriptions, $this->activeSubscription);

        session()->flash('success', __('Langganan berhasil ditambahkan.'));
    }

    public function openEditModal(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        $this->editingSubscriptionId = $sub->id;
        $this->status = $sub->status->value;
        $this->paymentReference = $sub->payment_reference ?? '';
        $this->notes = $sub->notes ?? '';
        $this->showEditModal = true;
    }

    public function updateSubscription(): void
    {
        $this->validate([
            'status' => ['required', 'string'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sub = Subscription::findOrFail($this->editingSubscriptionId);
        $sub->update([
            'status' => $this->status,
            'payment_reference' => $this->paymentReference ?: null,
            'notes' => $this->notes ?: null,
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        unset($this->subscriptions, $this->activeSubscription);

        session()->flash('success', __('Langganan berhasil diperbarui.'));
    }

    protected function resetForm(): void
    {
        $this->editingSubscriptionId = null;
        $this->selectedPlanId = null;
        $this->startsAt = '';
        $this->pricePaid = '';
        $this->paymentReference = '';
        $this->notes = '';
        $this->status = 'active';
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('admin.tenants')" wire:navigate />
        <div class="flex-1">
            <flux:heading size="xl">{{ $tenant->name }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola langganan untuk cafe ini.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openAddModal">
            {{ __('Tambah Langganan') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    {{-- Active Subscription Card --}}
    @if ($this->activeSubscription)
        @php $activeSub = $this->activeSubscription; @endphp
        <div class="rounded-xl border {{ $activeSub->isExpiringSoon() ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' }} p-5">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:icon.check-badge class="size-5 text-emerald-600" />
                        <flux:heading size="lg">{{ __('Langganan Aktif') }}</flux:heading>
                    </div>
                    <div class="mt-2 space-y-1 text-sm">
                        <div><strong>{{ __('Paket') }}:</strong> {{ $activeSub->plan->name }} ({{ $activeSub->plan->durationLabel() }})</div>
                        <div><strong>{{ __('Periode') }}:</strong> {{ $activeSub->starts_at->format('d M Y') }} - {{ $activeSub->ends_at->format('d M Y') }}</div>
                        <div><strong>{{ __('Dibayar') }}:</strong> Rp {{ number_format($activeSub->price_paid, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold {{ $activeSub->isExpiringSoon() ? 'text-amber-600' : 'text-emerald-600' }}">
                        {{ $activeSub->remainingDays() }}
                    </div>
                    <flux:text size="sm">{{ __('hari tersisa') }}</flux:text>
                    @if ($activeSub->isExpiringSoon())
                        <flux:badge variant="amber" size="sm" class="mt-1">{{ __('Segera berakhir') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>
    @else
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ __('Cafe ini tidak memiliki langganan aktif.') }}
        </flux:callout>
    @endif

    {{-- Subscription History --}}
    <div>
        <flux:heading size="lg" class="mb-3">{{ __('Riwayat Langganan') }}</flux:heading>
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Paket') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Periode') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Harga') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Ref. Pembayaran') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->subscriptions as $sub)
                        <tr wire:key="sub-{{ $sub->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $sub->plan->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $sub->plan->durationLabel() }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $sub->starts_at->format('d M Y') }} - {{ $sub->ends_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">Rp {{ number_format($sub->price_paid, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $sub->payment_reference ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge :variant="$sub->status->color()" size="sm">{{ $sub->status->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $sub->id }})" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                {{ __('Belum ada riwayat langganan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Subscription Modal --}}
    <flux:modal wire:model="showAddModal" class="max-w-lg">
        <form wire:submit="addSubscription" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah Langganan') }}</flux:heading>

            <flux:select wire:model.live="selectedPlanId" :label="__('Paket Langganan')" required>
                <option value="">{{ __('-- Pilih Paket --') }}</option>
                @foreach ($this->activePlans as $plan)
                    <option value="{{ $plan->id }}">
                        {{ $plan->name }} ({{ $plan->durationLabel() }}) - {{ $plan->formattedPrice() }}
                    </option>
                @endforeach
            </flux:select>

            <flux:input wire:model="startsAt" :label="__('Tanggal Mulai')" type="date" required />

            <flux:input wire:model="pricePaid" :label="__('Harga Dibayar (Rp)')" type="number" min="0" step="1000" required />

            <flux:input wire:model="paymentReference" :label="__('Referensi Pembayaran')" :placeholder="__('contoh: TRX-20260209-001, BCA Transfer')" />

            <flux:textarea wire:model="notes" :label="__('Catatan')" rows="2" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showAddModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Subscription Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-md">
        <form wire:submit="updateSubscription" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Langganan') }}</flux:heading>

            <flux:select wire:model="status" :label="__('Status')">
                @foreach (SubscriptionStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="paymentReference" :label="__('Referensi Pembayaran')" />

            <flux:textarea wire:model="notes" :label="__('Catatan')" rows="2" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Perbarui') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
