<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Pengaturan Platform')] class extends Component {
    use WithFileUploads;

    public string $whatsappNumber = '';
    public string $siteName = '';
    public string $siteTagline = '';

    public $siteLogo = null;
    public ?string $currentSiteLogo = null;

    public $siteFavicon = null;
    public ?string $currentSiteFavicon = null;

    public function mount(): void
    {
        $this->whatsappNumber = SystemSetting::get('whatsapp_number', '') ?? '';
        $this->siteName = SystemSetting::get('site_name', 'HsCaffeSystem') ?? 'HsCaffeSystem';
        $this->siteTagline = SystemSetting::get('site_tagline', '') ?? '';
        $this->currentSiteLogo = SystemSetting::get('site_logo');
        $this->currentSiteFavicon = SystemSetting::get('site_favicon');
    }

    public function save(): void
    {
        $this->validate([
            'whatsappNumber' => ['nullable', 'string', 'max:20'],
            'siteName' => ['required', 'string', 'max:255'],
            'siteTagline' => ['nullable', 'string', 'max:500'],
            'siteLogo' => ['nullable', 'image', 'max:2048'],
            'siteFavicon' => ['nullable', 'image', 'max:512', 'dimensions:max_width=512,max_height=512'],
        ]);

        SystemSetting::set('whatsapp_number', $this->whatsappNumber ?: null);
        SystemSetting::set('site_name', $this->siteName);
        SystemSetting::set('site_tagline', $this->siteTagline ?: null);

        if ($this->siteLogo) {
            $oldLogo = SystemSetting::get('site_logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            $path = $this->siteLogo->store('site', 'public');
            SystemSetting::set('site_logo', $path);
            $this->currentSiteLogo = $path;
            $this->siteLogo = null;
        }

        if ($this->siteFavicon) {
            $oldFavicon = SystemSetting::get('site_favicon');
            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }
            $path = $this->siteFavicon->store('site', 'public');
            SystemSetting::set('site_favicon', $path);
            $this->currentSiteFavicon = $path;
            $this->siteFavicon = null;
        }

        \App\Models\AuditLog::record('settings_update', 'Pengaturan platform diperbarui');

        $this->dispatch('settings-saved');
    }

    public function removeSiteLogo(): void
    {
        $current = SystemSetting::get('site_logo');
        if ($current) {
            Storage::disk('public')->delete($current);
            SystemSetting::set('site_logo', null);
        }
        $this->currentSiteLogo = null;
    }

    public function removeSiteFavicon(): void
    {
        $current = SystemSetting::get('site_favicon');
        if ($current) {
            Storage::disk('public')->delete($current);
            SystemSetting::set('site_favicon', null);
        }
        $this->currentSiteFavicon = null;
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Pengaturan Platform') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Kelola pengaturan umum platform, logo, favicon, dan kontak WhatsApp.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- General --}}
        <div class="rounded-xl border border-zinc-200 p-6 space-y-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Umum') }}</flux:heading>

            <flux:input wire:model="siteName" :label="__('Nama Platform')" required />
            <flux:input wire:model="siteTagline" :label="__('Tagline')" :placeholder="__('Sistem Manajemen Cafe Multi-Tenant')" />
        </div>

        {{-- WhatsApp --}}
        <div class="rounded-xl border border-zinc-200 p-6 space-y-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('WhatsApp Kontak') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Nomor ini akan ditampilkan di halaman utama sebagai link "Mulai Sekarang" untuk calon pelanggan.') }}</flux:text>

            <flux:input wire:model="whatsappNumber" :label="__('Nomor WhatsApp')" :placeholder="__('6281234567890')" :description="__('Format internasional tanpa + (contoh: 6281234567890)')" />
        </div>

        {{-- Logo --}}
        <div class="rounded-xl border border-zinc-200 p-6 space-y-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Logo Platform') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Logo yang tampil di halaman utama dan sidebar navigasi.') }}</flux:text>

            <div class="flex items-center gap-4">
                @if ($siteLogo)
                    <img src="{{ $siteLogo->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-xl border border-zinc-200 object-contain dark:border-zinc-700" />
                @elseif ($currentSiteLogo)
                    <img src="{{ Storage::url($currentSiteLogo) }}" alt="Logo" class="h-20 w-20 rounded-xl border border-zinc-200 object-contain dark:border-zinc-700" />
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                        <flux:icon.photo class="size-8 text-zinc-400" />
                    </div>
                @endif
                <div class="flex flex-col gap-2">
                    <label class="cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-center text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                        {{ __('Pilih Logo') }}
                        <input wire:model="siteLogo" type="file" accept="image/*" class="hidden" />
                    </label>
                    @if ($currentSiteLogo)
                        <button type="button" wire:click="removeSiteLogo" wire:confirm="{{ __('Hapus logo platform?') }}" class="text-sm text-red-500 hover:underline">
                            {{ __('Hapus Logo') }}
                        </button>
                    @endif
                </div>
            </div>
            <flux:text class="text-xs">{{ __('Format: JPG, PNG, maks. 2MB. Disarankan rasio 1:1.') }}</flux:text>
            @error('siteLogo')
                <flux:text class="text-xs text-red-500">{{ $message }}</flux:text>
            @enderror
        </div>

        {{-- Favicon --}}
        <div class="rounded-xl border border-zinc-200 p-6 space-y-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Favicon') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Ikon kecil yang tampil di tab browser. Disarankan ukuran 32x32 atau 64x64 pixel.') }}</flux:text>

            <div class="flex items-center gap-4">
                @if ($siteFavicon)
                    <img src="{{ $siteFavicon->temporaryUrl() }}" alt="Preview" class="h-16 w-16 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                @elseif ($currentSiteFavicon)
                    <img src="{{ Storage::url($currentSiteFavicon) }}" alt="Favicon" class="h-16 w-16 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                        <flux:icon.globe-alt class="size-6 text-zinc-400" />
                    </div>
                @endif
                <div class="flex flex-col gap-2">
                    <label class="cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-center text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                        {{ __('Pilih Favicon') }}
                        <input wire:model="siteFavicon" type="file" accept="image/*" class="hidden" />
                    </label>
                    @if ($currentSiteFavicon)
                        <button type="button" wire:click="removeSiteFavicon" wire:confirm="{{ __('Hapus favicon?') }}" class="text-sm text-red-500 hover:underline">
                            {{ __('Hapus Favicon') }}
                        </button>
                    @endif
                </div>
            </div>
            <flux:text class="text-xs">{{ __('Format: PNG, ICO, maks. 512KB, max 512x512px.') }}</flux:text>
            @error('siteFavicon')
                <flux:text class="text-xs text-red-500">{{ $message }}</flux:text>
            @enderror
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Simpan Pengaturan') }}
            </flux:button>
            <x-action-message class="me-3" on="settings-saved">
                {{ __('Tersimpan.') }}
            </x-action-message>
        </div>
    </form>
</div>
