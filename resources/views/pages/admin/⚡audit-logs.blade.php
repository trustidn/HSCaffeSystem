<?php

use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $filterAction = '';

    public string $search = '';

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function actionLabels(): array
    {
        return [
            'backup_create' => 'Backup Dibuat',
            'backup_delete' => 'Backup Dihapus',
            'backup_restore' => 'Restore Database',
            'tenant_create' => 'Cafe Dibuat',
            'tenant_delete' => 'Cafe Dihapus',
            'staff_create' => 'Staff Dibuat',
            'staff_delete' => 'Staff Dihapus',
            'user_delete' => 'Akun Dihapus',
            'settings_update' => 'Pengaturan Diperbarui',
        ];
    }

    #[Computed]
    public function logs(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->when($this->search, fn ($q) => $q->where('description', 'like', '%' . $this->search . '%'))
            ->latest('created_at')
            ->paginate(20);
    }

    /**
     * @return array<string, string>
     */
    private function actionBadgeVariant(string $action): string
    {
        return match (true) {
            str_contains($action, 'delete') => 'danger',
            str_contains($action, 'restore') => 'warning',
            str_contains($action, 'create') => 'success',
            default => 'default',
        };
    }
}; ?>

<div>
    <div class="mx-auto max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Audit Log</flux:heading>
                <flux:text class="mt-1">Riwayat operasi sensitif pada platform.</flux:text>
            </div>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari deskripsi..." icon="magnifying-glass" />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="filterAction" placeholder="Semua Aksi">
                    <flux:select.option value="">Semua Aksi</flux:select.option>
                    @foreach ($this->actionLabels as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Waktu</flux:table.column>
                <flux:table.column>User</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                <flux:table.column>IP</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->logs as $log)
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap text-xs">
                            {{ $log->created_at->format('d M Y H:i:s') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $log->user?->name ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $variant = match (true) {
                                    str_contains($log->action, 'delete') => 'danger',
                                    str_contains($log->action, 'restore') => 'warning',
                                    str_contains($log->action, 'create') => 'success',
                                    default => 'default',
                                };
                            @endphp
                            <flux:badge size="sm" :variant="$variant">
                                {{ $this->actionLabels[$log->action] ?? $log->action }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate">
                            {{ $log->description }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            {{ $log->ip_address ?? '-' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            Belum ada log audit.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $this->logs->links() }}
        </div>
    </div>
</div>
