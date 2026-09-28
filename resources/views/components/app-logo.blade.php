@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Wifone" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-4" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Wifone" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-4" />
        </x-slot>
    </flux:brand>
@endif
