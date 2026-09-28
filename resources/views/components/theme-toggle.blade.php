{{-- Switches between light and dark mode (Flux stores the choice in localStorage). --}}
<flux:button
    x-data
    variant="subtle"
    square
    {{ $attributes->merge(['class' => 'rounded-full!']) }}
    x-on:click="$flux.dark = ! $flux.dark"
    x-bind:aria-pressed="$flux.dark.toString()"
    :aria-label="__('Dark mode')"
>
    <flux:icon.moon variant="mini" class="dark:hidden" />
    <flux:icon.sun variant="mini" class="not-dark:hidden" />
</flux:button>
