<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Staff')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';
    public ?int $editingUserId = null;

    #[Computed]
    public function staffMembers()
    {
        return User::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($this->search, fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
            )
            ->latest()
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->showEditModal = true;
    }

    public function createStaff(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::staffRoles(), 'value'))],
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'tenant_id' => auth()->user()->tenant_id,
            'email_verified_at' => now(),
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        unset($this->staffMembers);
    }

    public function updateStaff(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->editingUserId],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::staffRoles(), 'value'))],
        ]);

        $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingUserId);
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $user->update($data);
        $this->showEditModal = false;
        $this->resetForm();
        unset($this->staffMembers);
    }

    public function confirmDelete(int $userId): void
    {
        $this->editingUserId = $userId;
        $this->showDeleteModal = true;
    }

    public function deleteStaff(): void
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingUserId);
        if ($user->id === auth()->id()) {
            return;
        }
        $user->delete();
        $this->showDeleteModal = false;
        unset($this->staffMembers);
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = '';
        $this->editingUserId = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kelola Staff') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola staff untuk cafe Anda.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Tambah Staff') }}</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari staff...')" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->staffMembers as $member)
                    <tr wire:key="staff-{{ $member->id }}">
                        <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $member->email }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $member->role->label() }}</flux:badge></td>
                        <td class="px-4 py-3 text-right">
                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $member->id }})" />
                            @if ($member->id !== auth()->id())
                                <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $member->id }})" class="text-red-500" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada staff.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $this->staffMembers->links() }}</div>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-md">
        <form wire:submit="createStaff" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah Staff') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Nama')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" required />
            <flux:select wire:model="role" :label="__('Role')">
                <option value="">{{ __('Pilih role...') }}</option>
                @foreach (UserRole::staffRoles() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-md">
        <form wire:submit="updateStaff" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Staff') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Nama')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" :description="__('Kosongkan jika tidak mengubah.')" />
            <flux:select wire:model="role" :label="__('Role')">
                @foreach (UserRole::staffRoles() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Perbarui') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Staff') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteStaff">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
