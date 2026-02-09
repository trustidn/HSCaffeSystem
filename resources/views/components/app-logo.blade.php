@props([
    'sidebar' => false,
])

@php
    $customLogo = \App\Models\SystemSetting::get('site_logo');
    $customName = \App\Models\SystemSetting::get('site_name', 'HS Caffe System');
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$customName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if ($customLogo)
                <img src="{{ Storage::url($customLogo) }}" alt="{{ $customName }}" class="size-8 rounded-md object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$customName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if ($customLogo)
                <img src="{{ Storage::url($customLogo) }}" alt="{{ $customName }}" class="size-8 rounded-md object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
