<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main-content" role="main" tabindex="-1" class="outline-none max-lg:pb-28">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
