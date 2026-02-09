<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $slogan = '';

    #[Validate('nullable|image|max:2048')]
    public $logo = null;

    public ?string $currentLogo = null;

    #[Validate('required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/')]
    public string $primaryColor = '#6366f1';

    #[Validate('required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/')]
    public string $secondaryColor = '#818cf8';

    #[Validate('nullable|string|max:500')]
    public ?string $address = '';

    #[Validate('nullable|string|max:20|regex:/^[0-9+\-\s()]*$/')]
    public ?string $phone = '';

    #[Validate('nullable|email|max:255')]
    public ?string $email = '';

    public function mount(): void
    {
        $tenant = Auth::user()->tenant;

        if (! $tenant) {
            return;
        }

        $this->name = $tenant->name;
        $this->slogan = $tenant->slogan ?? '';
        $this->currentLogo = $tenant->logo;
        $this->primaryColor = $tenant->primary_color ?? '#6366f1';
        $this->secondaryColor = $tenant->secondary_color ?? '#818cf8';
        $this->address = $tenant->address ?? '';
        $this->phone = $tenant->phone ?? '';
        $this->email = $tenant->email ?? '';
    }

    public function save(): void
    {
        $this->validate();

        $tenant = Auth::user()->tenant;

        if (! $tenant) {
            return;
        }

        $data = [
            'name' => $this->name,
            'slogan' => $this->slogan ?: null,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
        ];

        if ($this->logo) {
            // Delete old logo if exists
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }

            $data['logo'] = $this->logo->store('logos', 'public');
        }

        $tenant->update($data);

        $this->logo = null;

        $this->dispatch('cafe-updated');
    }

    public function removeLogo(): void
    {
        $tenant = Auth::user()->tenant;

        if (! $tenant || ! $tenant->logo) {
            return;
        }

        Storage::disk('public')->delete($tenant->logo);
        $tenant->update(['logo' => null]);
        $this->currentLogo = null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Cafe')" :subheading="__('Kelola branding dan informasi cafe Anda')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            {{-- Logo --}}
            <div>
                <flux:label>{{ __('Logo Cafe') }}</flux:label>
                <div class="mt-2 flex items-center gap-4">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-xl border border-zinc-200 object-cover dark:border-zinc-700" />
                    @elseif ($currentLogo)
                        <img src="{{ Storage::url($currentLogo) }}" alt="Logo" class="h-20 w-20 rounded-xl border border-zinc-200 object-cover dark:border-zinc-700" />
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                            <flux:icon.photo class="size-8 text-zinc-400" />
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        <label class="cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                            {{ __('Pilih Gambar') }}
                            <input wire:model="logo" type="file" accept="image/*" class="hidden" />
                        </label>
                        @if ($currentLogo)
                            <button type="button" wire:click="removeLogo" wire:confirm="{{ __('Hapus logo cafe?') }}" class="text-sm text-red-500 hover:underline">
                                {{ __('Hapus Logo') }}
                            </button>
                        @endif
                    </div>
                </div>
                <flux:text class="mt-1 text-xs">{{ __('Format: JPG, PNG, maks. 2MB') }}</flux:text>
                @error('logo')
                    <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            {{-- Name --}}
            <flux:input wire:model="name" :label="__('Nama Cafe')" required />

            {{-- Slogan --}}
            <flux:input wire:model="slogan" :label="__('Slogan')" :placeholder="__('Contoh: Kopi terbaik untuk harimu')" />

            {{-- Colors --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:label>{{ __('Warna Utama') }}</flux:label>
                    <div class="mt-2 flex items-center gap-3">
                        <input wire:model.live="primaryColor" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                        <flux:input wire:model.live="primaryColor" class="font-mono" maxlength="7" />
                    </div>
                    @error('primaryColor')
                        <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div>
                    <flux:label>{{ __('Warna Sekunder') }}</flux:label>
                    <div class="mt-2 flex items-center gap-3">
                        <input wire:model.live="secondaryColor" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                        <flux:input wire:model.live="secondaryColor" class="font-mono" maxlength="7" />
                    </div>
                    @error('secondaryColor')
                        <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            {{-- Color Preview --}}
            <div>
                <flux:label>{{ __('Preview Warna') }}</flux:label>
                <div class="mt-2 flex items-center gap-3">
                    <div class="flex h-12 flex-1 items-center justify-center rounded-lg text-sm font-medium text-white" style="background-color: {{ $primaryColor }}">
                        {{ __('Warna Utama') }}
                    </div>
                    <div class="flex h-12 flex-1 items-center justify-center rounded-lg text-sm font-medium text-white" style="background-color: {{ $secondaryColor }}">
                        {{ __('Warna Sekunder') }}
                    </div>
                </div>
            </div>

            <flux:separator />

            {{-- Contact Info --}}
            <flux:heading size="md">{{ __('Informasi Kontak') }}</flux:heading>

            <flux:textarea wire:model="address" :label="__('Alamat')" rows="2" :placeholder="__('Alamat lengkap cafe')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="phone" :label="__('Telepon')" :placeholder="__('021-xxxxxxx')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" :placeholder="__('info@cafe.id')" />
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Simpan') }}
                </flux:button>

                <x-action-message class="me-3" on="cafe-updated">
                    {{ __('Tersimpan.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
