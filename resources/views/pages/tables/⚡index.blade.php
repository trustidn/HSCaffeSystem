<?php

use App\Enums\TableStatus;
use App\Models\Table;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manajemen Meja')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showQrModal = false;

    public ?int $editingTableId = null;
    public string $number = '';
    public string $section = '';
    public int $capacity = 4;
    public string $qrUrl = '';
    public string $qrSvg = '';
    public string $qrTableName = '';

    #[Computed]
    public function tables()
    {
        return Table::query()
            ->orderBy('created_at')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $table = Table::findOrFail($id);
        $this->editingTableId = $table->id;
        $this->number = $table->number;
        $this->section = $table->section ?? '';
        $this->capacity = $table->capacity;
        $this->showEditModal = true;
    }

    public function createTable(): void
    {
        $this->validate([
            'number' => ['required', 'string', 'max:20'],
            'section' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        Table::create([
            'number' => $this->number,
            'section' => $this->section ?: null,
            'capacity' => $this->capacity,
            'qr_token' => Str::random(32),
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        unset($this->tables);
    }

    public function updateTable(): void
    {
        $this->validate([
            'number' => ['required', 'string', 'max:20'],
            'section' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $table = Table::findOrFail($this->editingTableId);
        $table->update([
            'number' => $this->number,
            'section' => $this->section ?: null,
            'capacity' => $this->capacity,
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        unset($this->tables);
    }

    public function updateStatus(int $id, string $status): void
    {
        $table = Table::findOrFail($id);
        $table->update(['status' => $status]);
        unset($this->tables);
    }

    public function showQr(int $id): void
    {
        $table = Table::findOrFail($id);
        $tenant = auth()->user()->tenant;
        $this->qrUrl = url('/order/'.$tenant->slug.'?table='.$table->qr_token);
        $this->qrTableName = __('Meja').' '.$table->number;

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'svgViewBoxSize' => 250,
            'addQuietzone' => true,
            'markupDark' => '#000000',
            'markupLight' => '#ffffff',
            'drawLightModules' => true,
        ]);

        $this->qrSvg = (new QRCode($options))->render($this->qrUrl);
        $this->showQrModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->editingTableId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteTable(): void
    {
        Table::findOrFail($this->editingTableId)->delete();
        $this->showDeleteModal = false;
        $this->editingTableId = null;
        unset($this->tables);
    }

    protected function resetForm(): void
    {
        $this->number = '';
        $this->section = '';
        $this->capacity = 4;
        $this->editingTableId = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Meja') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola meja, status, dan QR code untuk self-order.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Tambah Meja') }}
        </flux:button>
    </div>

    {{-- Table Grid --}}
    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
        @forelse ($this->tables as $table)
            <div wire:key="table-{{ $table->id }}" class="rounded-xl border p-4 space-y-3 {{ $table->status->bgClass() }}">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Meja') }} {{ $table->number }}</flux:heading>
                    <flux:badge :variant="$table->status->color()" size="sm">{{ $table->status->label() }}</flux:badge>
                </div>

                <div class="space-y-1 text-sm text-zinc-500">
                    @if ($table->section)
                        <div class="flex items-center gap-1">
                            <flux:icon.map-pin class="size-4" />
                            {{ $table->section }}
                        </div>
                    @endif
                    <div class="flex items-center gap-1">
                        <flux:icon.users class="size-4" />
                        {{ $table->capacity }} {{ __('orang') }}
                    </div>
                </div>

                {{-- Status Buttons --}}
                <div class="flex flex-wrap gap-1">
                    @foreach (TableStatus::cases() as $status)
                        @if ($table->status !== $status)
                            <flux:button size="xs" wire:click="updateStatus({{ $table->id }}, '{{ $status->value }}')">
                                {{ $status->label() }}
                            </flux:button>
                        @endif
                    @endforeach
                </div>

                {{-- Actions --}}
                <div class="flex gap-1 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <flux:button variant="ghost" size="sm" icon="qr-code" wire:click="showQr({{ $table->id }})" />
                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $table->id }})" />
                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $table->id }})" class="text-red-500" />
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-zinc-500">
                {{ __('Belum ada meja. Tambahkan meja baru untuk memulai.') }}
            </div>
        @endforelse
    </div>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-md">
        <form wire:submit="createTable" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah Meja') }}</flux:heading>
            <flux:input wire:model="number" :label="__('Nomor Meja')" required :placeholder="__('contoh: 1, A1, VIP-1')" />
            <flux:input wire:model="section" :label="__('Area/Section')" :placeholder="__('contoh: Indoor, Outdoor, VIP')" />
            <flux:input wire:model="capacity" :label="__('Kapasitas (orang)')" type="number" min="1" required />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-md">
        <form wire:submit="updateTable" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Meja') }}</flux:heading>
            <flux:input wire:model="number" :label="__('Nomor Meja')" required />
            <flux:input wire:model="section" :label="__('Area/Section')" />
            <flux:input wire:model="capacity" :label="__('Kapasitas (orang)')" type="number" min="1" required />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Perbarui') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- QR Modal --}}
    <flux:modal wire:model="showQrModal" class="max-w-sm">
        <div class="space-y-4 text-center">
            <flux:heading size="lg">{{ $qrTableName }}</flux:heading>

            @if ($qrSvg)
                <div class="mx-auto w-56 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700" id="qr-container">
                    <img src="{{ $qrSvg }}" alt="QR Code {{ $qrTableName }}" class="h-auto w-full" />
                </div>
            @endif

            <div class="rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                <flux:text size="sm" class="break-all font-mono">{{ $qrUrl }}</flux:text>
            </div>

            <flux:text size="sm">{{ __('Scan QR code ini atau buka URL di atas untuk memesan dari meja ini.') }}</flux:text>

            <div class="flex gap-2">
                <flux:button wire:click="$set('showQrModal', false)" class="flex-1">{{ __('Tutup') }}</flux:button>
                <flux:button variant="primary" class="flex-1" x-on:click="
                    const imgEl = document.querySelector('#qr-container img');
                    if (!imgEl) return;
                    const canvas = document.createElement('canvas');
                    canvas.width = 500; canvas.height = 500;
                    const ctx = canvas.getContext('2d');
                    const img = new Image();
                    img.onload = () => {
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, 500, 500);
                        ctx.drawImage(img, 0, 0, 500, 500);
                        const a = document.createElement('a');
                        a.download = 'qr-{{ Str::slug($qrTableName) }}.png';
                        a.href = canvas.toDataURL('image/png');
                        a.click();
                    };
                    img.src = imgEl.src;
                " icon="arrow-down-tray">
                    {{ __('Download') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Meja') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin ingin menghapus meja ini?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteTable">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
