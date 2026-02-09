<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Staff')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public string $filterTenant = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';
    public string $tenantId = '';
    public ?int $editingUserId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('tenant')
            ->when($this->search, fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
            )
            ->when($this->filterRole, fn ($q) => $q->where('role', $this->filterRole))
            ->when($this->filterTenant, fn ($q) => $q->where('tenant_id', $this->filterTenant))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->tenantId = (string) ($user->tenant_id ?? '');
        $this->password = '';
        $this->showEditModal = true;
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'tenantId' => ['nullable', 'exists:tenants,id'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'tenant_id' => $validated['tenantId'] ?: null,
            'email_verified_at' => now(),
        ]);

        \App\Models\AuditLog::record('staff_create', 'User dibuat: ' . $validated['email'], [
            'role' => $validated['role'],
            'tenant_id' => $validated['tenantId'] ?: null,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        unset($this->users);

        session()->flash('success', 'User berhasil ditambahkan.');
    }

    public function updateUser(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->editingUserId],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'tenantId' => ['nullable', 'exists:tenants,id'],
        ]);

        $user = User::findOrFail($this->editingUserId);
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'tenant_id' => $validated['tenantId'] ?: null,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        $this->showEditModal = false;
        $this->resetForm();
        unset($this->users);

        session()->flash('success', 'User berhasil diperbarui.');
    }

    public function confirmDelete(int $userId): void
    {
        $this->editingUserId = $userId;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->editingUserId);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Tidak bisa menghapus akun sendiri.');
            $this->showDeleteModal = false;
            return;
        }

        \App\Models\AuditLog::record('staff_delete', 'User dihapus: ' . $user->email, [
            'deleted_user_id' => $user->id,
        ]);

        $user->delete();
        $this->showDeleteModal = false;
        $this->editingUserId = null;
        unset($this->users);

        session()->flash('success', 'User berhasil dihapus.');
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = '';
        $this->tenantId = '';
        $this->editingUserId = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kelola Staff') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola semua pengguna dan staf di platform.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Tambah User') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari user...')" />
        </div>
        <flux:select wire:model.live="filterRole" class="w-40">
            <option value="">{{ __('Semua Role') }}</option>
            @foreach (UserRole::cases() as $r)
                <option value="{{ $r->value }}">{{ $r->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterTenant" class="w-48">
            <option value="">{{ __('Semua Cafe') }}</option>
            @foreach ($this->tenants as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Cafe') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                <span class="font-medium">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :variant="$user->isSuperAdmin() ? 'danger' : 'default'">
                                {{ $user->role->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $user->tenant?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $user->id }})" />
                                @if ($user->id !== auth()->id())
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $user->id }})" class="text-red-500 hover:text-red-700" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                            {{ __('Tidak ada user ditemukan.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->users->links() }}</div>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="createUser" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah User Baru') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Nama')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" required />

            <flux:select wire:model="role" :label="__('Role')">
                <option value="">{{ __('Pilih role...') }}</option>
                @foreach (UserRole::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="tenantId" :label="__('Cafe')" :description="__('Kosongkan untuk Super Admin.')">
                <option value="">{{ __('Tidak ada (Super Admin)') }}</option>
                @foreach ($this->tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-lg">
        <form wire:submit="updateUser" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Nama')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" :description="__('Kosongkan jika tidak ingin mengubah password.')" />

            <flux:select wire:model="role" :label="__('Role')">
                <option value="">{{ __('Pilih role...') }}</option>
                @foreach (UserRole::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="tenantId" :label="__('Cafe')">
                <option value="">{{ __('Tidak ada (Super Admin)') }}</option>
                @foreach ($this->tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Perbarui') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus User') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin ingin menghapus user ini?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteUser">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
