<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => null])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
        <header class="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-xl text-lg font-bold focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <span class="grid size-9 place-items-center rounded-xl bg-accent text-accent-foreground">
                    <x-app-logo-icon class="size-4" />
                </span>
                Wifone
            </a>

            <div class="ms-auto flex items-center gap-2">
                <x-theme-toggle />

                @auth
                    <flux:button variant="primary" :href="route('dashboard')" class="rounded-full!">{{ __('Open Wifone') }}</flux:button>
                @else
                    <flux:button variant="ghost" :href="route('login')" class="rounded-full!">{{ __('Log in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button variant="primary" :href="route('register')" class="rounded-full! max-sm:hidden">{{ __('Sign up') }}</flux:button>
                    @endif
                @endauth
            </div>
        </header>

        <main class="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pt-6 pb-16 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:pt-16">
            <div class="flex flex-col gap-6 motion-safe:animate-[rise_.5s_ease-out]">
                <p class="text-sm font-semibold tracking-wider text-accent-content uppercase">{{ __('Voice calls over Wi-Fi and mobile data') }}</p>
                <h1 class="text-4xl leading-tight font-bold tracking-tight text-balance sm:text-5xl">
                    {{ __('Call anyone on Wifone, straight from your browser.') }}
                </h1>
                <p class="max-w-prose text-lg text-zinc-600 dark:text-zinc-300">
                    {{ __('See who is online, tap to call, and talk. No phone number, no app store. Add it to your home screen and it works like a phone app.') }}
                </p>

                <div class="flex flex-wrap gap-3">
                    @auth
                        <flux:button variant="primary" icon="phone" :href="route('dashboard')" class="rounded-full! px-6!">{{ __('Open your contacts') }}</flux:button>
                    @else
                        @if (Route::has('register'))
                            <flux:button variant="primary" :href="route('register')" class="rounded-full! px-6!">{{ __('Create a free account') }}</flux:button>
                        @endif
                        <flux:button :href="route('login')" class="rounded-full! px-6!">{{ __('Log in') }}</flux:button>
                    @endauth
                </div>

                <ul class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'wifi', 'title' => __('Any connection'), 'text' => __('Works on Wi-Fi or mobile data.')],
                        ['icon' => 'device-phone-mobile', 'title' => __('Every device'), 'text' => __('Rings wherever you are signed in.')],
                        ['icon' => 'clock', 'title' => __('Call log'), 'text' => __('Missed calls are one tap from a call back.')],
                    ] as $feature)
                        <li class="flex flex-col gap-1">
                            <flux:icon :name="$feature['icon']" class="mb-1 size-6 text-accent-content" />
                            <span class="font-semibold">{{ $feature['title'] }}</span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $feature['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Illustration of an incoming call --}}
            <div class="flex justify-center" aria-hidden="true">
                <div class="relative flex h-[30rem] w-60 flex-col items-center overflow-hidden rounded-[2.5rem] bg-linear-to-b from-[#0f8487] to-[#0b5f66] px-6 pt-14 pb-10 text-white shadow-2xl ring-8 ring-white motion-safe:animate-[rise_.7s_ease-out] dark:ring-zinc-800">
                    <div class="relative grid size-24 place-items-center">
                        <span class="absolute inset-0 animate-ring rounded-full bg-white/20 motion-reduce:animate-none"></span>
                        <span class="absolute inset-0 animate-ring rounded-full bg-white/15 [animation-delay:1.2s] motion-reduce:animate-none"></span>
                        <span class="relative grid size-24 place-items-center rounded-full bg-white/15 text-3xl font-semibold">PN</span>
                    </div>
                    <span class="mt-8 text-2xl font-bold">Priya Nair</span>
                    <span class="text-sm text-teal-100/80">{{ __('Wifone audio call') }}</span>
                    <div class="mt-auto flex w-full justify-between px-2">
                        <span class="bg-hangup grid size-14 place-items-center rounded-full"><flux:icon.phone variant="solid" class="size-6 rotate-[135deg]" /></span>
                        <span class="bg-online grid size-14 place-items-center rounded-full motion-safe:animate-bounce"><flux:icon.phone variant="solid" class="size-6" /></span>
                    </div>
                </div>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
