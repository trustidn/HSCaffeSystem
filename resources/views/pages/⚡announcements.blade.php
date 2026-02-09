<?php

use App\Models\Announcement;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengumuman')] class extends Component {
    public string $filterType = '';

    #[Computed]
    public function announcements()
    {
        return Announcement::published()
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->latest('published_at')
            ->get();
    }

    #[Computed]
    public function typeCounts(): array
    {
        $counts = Announcement::published()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'all' => array_sum($counts),
            'update' => $counts['update'] ?? 0,
            'info' => $counts['info'] ?? 0,
            'success' => $counts['success'] ?? 0,
            'warning' => $counts['warning'] ?? 0,
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Pengumuman') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Informasi terbaru tentang fitur dan update platform.') }}</flux:text>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2">
        <button wire:click="$set('filterType', '')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filterType === '' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
            Semua
            <span class="rounded-full {{ $filterType === '' ? 'bg-white/20 dark:bg-zinc-900/20' : 'bg-zinc-200 dark:bg-zinc-700' }} px-1.5 py-0.5 text-xs">{{ $this->typeCounts['all'] }}</span>
        </button>
        <button wire:click="$set('filterType', 'update')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filterType === 'update' ? 'bg-indigo-600 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
            Update
            <span class="rounded-full {{ $filterType === 'update' ? 'bg-white/20' : 'bg-zinc-200 dark:bg-zinc-700' }} px-1.5 py-0.5 text-xs">{{ $this->typeCounts['update'] }}</span>
        </button>
        <button wire:click="$set('filterType', 'info')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filterType === 'info' ? 'bg-sky-600 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
            Info
            <span class="rounded-full {{ $filterType === 'info' ? 'bg-white/20' : 'bg-zinc-200 dark:bg-zinc-700' }} px-1.5 py-0.5 text-xs">{{ $this->typeCounts['info'] }}</span>
        </button>
        <button wire:click="$set('filterType', 'success')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filterType === 'success' ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
            Sukses
            <span class="rounded-full {{ $filterType === 'success' ? 'bg-white/20' : 'bg-zinc-200 dark:bg-zinc-700' }} px-1.5 py-0.5 text-xs">{{ $this->typeCounts['success'] }}</span>
        </button>
        <button wire:click="$set('filterType', 'warning')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filterType === 'warning' ? 'bg-amber-600 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
            Peringatan
            <span class="rounded-full {{ $filterType === 'warning' ? 'bg-white/20' : 'bg-zinc-200 dark:bg-zinc-700' }} px-1.5 py-0.5 text-xs">{{ $this->typeCounts['warning'] }}</span>
        </button>
    </div>

    {{-- Announcements List --}}
    @if ($this->announcements->isEmpty())
        <div class="rounded-xl border border-zinc-200 p-12 text-center dark:border-zinc-700">
            <flux:icon.megaphone class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
            <flux:heading size="lg" class="mt-4">{{ __('Tidak ada pengumuman') }}</flux:heading>
            <flux:text class="mt-1">{{ $filterType ? __('Tidak ada pengumuman dengan tipe ini.') : __('Belum ada pengumuman saat ini.') }}</flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->announcements as $announcement)
                @php
                    $config = match($announcement->type) {
                        'update' => ['bg' => 'border-l-indigo-500', 'icon' => 'rocket-launch', 'iconColor' => 'text-indigo-600 dark:text-indigo-400', 'iconBg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'badge' => 'indigo', 'badgeLabel' => 'Update'],
                        'warning' => ['bg' => 'border-l-amber-500', 'icon' => 'exclamation-triangle', 'iconColor' => 'text-amber-600 dark:text-amber-400', 'iconBg' => 'bg-amber-100 dark:bg-amber-900/30', 'badge' => 'amber', 'badgeLabel' => 'Peringatan'],
                        'success' => ['bg' => 'border-l-emerald-500', 'icon' => 'check-circle', 'iconColor' => 'text-emerald-600 dark:text-emerald-400', 'iconBg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'badge' => 'emerald', 'badgeLabel' => 'Sukses'],
                        default => ['bg' => 'border-l-sky-500', 'icon' => 'information-circle', 'iconColor' => 'text-sky-600 dark:text-sky-400', 'iconBg' => 'bg-sky-100 dark:bg-sky-900/30', 'badge' => 'sky', 'badgeLabel' => 'Info'],
                    };
                @endphp
                <div class="rounded-xl border border-zinc-200 border-l-4 {{ $config['bg'] }} bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex gap-4">
                        <div class="shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $config['iconBg'] }}">
                                <flux:icon :name="$config['icon']" class="size-5 {{ $config['iconColor'] }}" />
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $announcement->title }}</h3>
                                <flux:badge size="sm" :color="$config['badge']">{{ $config['badgeLabel'] }}</flux:badge>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $announcement->content }}</p>
                            <div class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $announcement->published_at->translatedFormat('d F Y, H:i') }} &middot; {{ $announcement->published_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
