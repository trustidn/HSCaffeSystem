<?php

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengumuman')] class extends Component {
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingId = null;
    public ?int $deletingId = null;
    public string $title = '';
    public string $content = '';
    public string $type = 'info';
    public bool $isActive = true;
    public string $publishedAt = '';

    #[Computed]
    public function announcements()
    {
        return Announcement::query()
            ->with('creator')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->publishedAt = now()->format('Y-m-d\TH:i');
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->content = $announcement->content;
        $this->type = $announcement->type;
        $this->isActive = $announcement->is_active;
        $this->publishedAt = $announcement->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:info,update,warning,success'],
            'isActive' => ['boolean'],
            'publishedAt' => ['required', 'date'],
        ]);

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'published_at' => $this->publishedAt,
        ];

        if ($this->editingId) {
            Announcement::where('id', $this->editingId)->update($data);
        } else {
            $data['created_by'] = Auth::id();
            Announcement::create($data);
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->announcements);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteAnnouncement(): void
    {
        if ($this->deletingId) {
            Announcement::where('id', $this->deletingId)->delete();
        }
        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->announcements);
    }

    public function toggleActive(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update(['is_active' => ! $announcement->is_active]);
        unset($this->announcements);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->content = '';
        $this->type = 'info';
        $this->isActive = true;
        $this->publishedAt = '';
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Pengumuman') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola pengumuman fitur terbaru dan informasi penting untuk pengguna.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Buat Pengumuman') }}
        </flux:button>
    </div>

    {{-- Announcements List --}}
    @if ($this->announcements->isEmpty())
        <div class="rounded-xl border border-zinc-200 p-12 text-center dark:border-zinc-700">
            <flux:icon.megaphone class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
            <flux:heading size="lg" class="mt-4">{{ __('Belum ada pengumuman') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Buat pengumuman pertama untuk memberitahu pengguna tentang fitur terbaru.') }}</flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->announcements as $announcement)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2.5">
                                @php
                                    $typeConfig = match($announcement->type) {
                                        'update' => ['color' => 'indigo', 'label' => 'Update'],
                                        'warning' => ['color' => 'amber', 'label' => 'Peringatan'],
                                        'success' => ['color' => 'emerald', 'label' => 'Sukses'],
                                        default => ['color' => 'sky', 'label' => 'Info'],
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$typeConfig['color']">{{ $typeConfig['label'] }}</flux:badge>
                                @if ($announcement->is_active)
                                    <flux:badge size="sm" color="emerald" variant="outline">Aktif</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc" variant="outline">Nonaktif</flux:badge>
                                @endif
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-zinc-900 dark:text-white">{{ $announcement->title }}</h3>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $announcement->content }}</p>
                            <div class="mt-3 flex items-center gap-4 text-xs text-zinc-400 dark:text-zinc-500">
                                <span>{{ $announcement->published_at?->translatedFormat('d M Y, H:i') ?? '-' }}</span>
                                @if ($announcement->creator)
                                    <span>oleh {{ $announcement->creator->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $announcement->id }})" />
                            <flux:button variant="ghost" size="sm" icon="{{ $announcement->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleActive({{ $announcement->id }})" />
                            <flux:button variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600" wire:click="confirmDelete({{ $announcement->id }})" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Form Modal --}}
    <flux:modal wire:model.self="showFormModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit Pengumuman') : __('Buat Pengumuman Baru') }}</flux:heading>

            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="title" :label="__('Judul')" required :placeholder="__('Contoh: Fitur baru - Kitchen Display System')" />

                <flux:textarea wire:model="content" :label="__('Isi Pengumuman')" required rows="5" :placeholder="__('Jelaskan detail pengumuman...')" />

                <flux:select wire:model="type" :label="__('Tipe')">
                    <flux:select.option value="info">Info</flux:select.option>
                    <flux:select.option value="update">Update / Fitur Baru</flux:select.option>
                    <flux:select.option value="success">Sukses</flux:select.option>
                    <flux:select.option value="warning">Peringatan</flux:select.option>
                </flux:select>

                <flux:input wire:model="publishedAt" :label="__('Tanggal Publish')" type="datetime-local" required />

                <flux:checkbox wire:model="isActive" :label="__('Aktifkan langsung')" />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Batal') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ $editingId ? __('Simpan Perubahan') : __('Buat Pengumuman') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="w-full max-w-sm">
        <div class="space-y-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.trash class="size-7 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Hapus Pengumuman?') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Pengumuman yang dihapus tidak dapat dikembalikan.') }}</flux:text>
            </div>
            <div class="flex justify-center gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteAnnouncement">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
