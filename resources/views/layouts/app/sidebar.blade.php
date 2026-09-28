<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <a href="#main-content" class="sr-only z-50 rounded-lg bg-accent px-4 py-2 font-medium text-accent-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
            {{ __('Skip to content') }}
        </a>

        <flux:sidebar sticky role="navigation" :aria-label="__('Main')" class="max-lg:hidden! border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="users" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Contacts') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="clock" :href="route('calls.index')" :current="request()->routeIs('calls.index')" wire:navigate>
                    {{ __('Recents') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="flex items-center justify-between px-2">
                <flux:text size="sm">{{ __('Appearance') }}</flux:text>
                <x-theme-toggle />
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:spacer />

            <x-theme-toggle class="me-1" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        {{-- Mobile tab bar --}}
        <nav
            aria-label="{{ __('Main') }}"
            class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-3 border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-zinc-800 dark:bg-zinc-900/95"
        >
            @foreach ([
                ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'users', 'label' => __('Contacts')],
                ['route' => 'calls.index', 'active' => 'calls.index', 'icon' => 'clock', 'label' => __('Recents')],
                ['route' => 'profile.edit', 'active' => ['profile.edit', 'security.edit', 'appearance.edit'], 'icon' => 'cog-6-tooth', 'label' => __('Settings')],
            ] as $tab)
                @php($isCurrentTab = request()->routeIs($tab['active']))
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if ($isCurrentTab) aria-current="page" @endif
                    @class([
                        'flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium',
                        'text-accent-content' => $isCurrentTab,
                        'text-zinc-500 dark:text-zinc-400' => ! $isCurrentTab,
                    ])
                >
                    <flux:icon :name="$tab['icon']" :variant="$isCurrentTab ? 'solid' : 'outline'" class="size-6" />
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Persisted so an in-progress call (and its <audio> element) survives wire:navigate page changes --}}
        @persist('call-ui')
            @include('partials.call-ui')
        @endpersist

        @fluxScripts
    </body>
</html>
