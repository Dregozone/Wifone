<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <main class="flex min-h-svh flex-col items-center justify-center gap-8 px-4 py-10 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-xl text-xl font-bold text-zinc-900 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none dark:text-white" wire:navigate>
                <span class="grid size-10 place-items-center rounded-xl bg-accent text-accent-foreground shadow-sm">
                    <x-app-logo-icon class="size-5" />
                </span>
                Wifone
            </a>
            <div class="flex w-full max-w-sm flex-col gap-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-zinc-200/70 motion-safe:animate-[rise_.4s_ease-out] sm:p-8 dark:bg-zinc-900 dark:ring-zinc-800">
                {{ $slot }}
            </div>
        </main>
        @fluxScripts
    </body>
</html>
