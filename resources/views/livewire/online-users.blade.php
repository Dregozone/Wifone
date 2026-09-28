<div
    class="mx-auto w-full max-w-2xl"
    x-data="{
        search: '',
        people: {{ Js::from($users->map->only('id', 'name')->values()) }},
        matches(name) {
            return name.toLowerCase().includes(this.search.trim().toLowerCase());
        },
        countFor(online) {
            return this.people.filter((person) => this.$store.presence.isOnline(person.id) === online && this.matches(person.name)).length;
        },
    }"
>
    <div class="mb-6 flex flex-col gap-4">
        <flux:heading size="xl" level="1" class="text-2xl! font-bold!">{{ __('Contacts') }}</flux:heading>

        @if ($users->isNotEmpty())
            <flux:input icon="magnifying-glass" x-model="search" :placeholder="__('Search people')" :aria-label="__('Search people')" />
        @endif
    </div>

    @if ($users->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-white px-6 py-12 text-center ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <flux:icon.users class="size-8 text-zinc-400" />
            <flux:heading>{{ __('No one else is here yet') }}</flux:heading>
            <flux:text>{{ __('People who sign up will show up here, ready to call.') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @foreach (['online' => true, 'offline' => false] as $group => $showsOnlineUsers)
                <section x-show="countFor({{ Js::from($showsOnlineUsers) }}) > 0" @if ($showsOnlineUsers) x-cloak @endif>
                    <h2 class="px-3 pb-2 text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                        {{ $showsOnlineUsers ? __('Online') : __('Offline') }}
                        <span class="tabular-nums">· <span x-text="countFor({{ Js::from($showsOnlineUsers) }})"></span></span>
                    </h2>

                    <ul class="flex flex-col gap-0.5 rounded-2xl motion-safe:animate-[rise_.35s_ease-out] bg-white p-1.5 shadow-xs ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
                        @foreach ($users as $user)
                            <li
                                wire:key="{{ $group }}-user-{{ $user->id }}"
                                x-data="{ userId: {{ $user->id }}, userName: {{ Js::from($user->name) }} }"
                                x-show="$store.presence.isOnline(userId) === {{ Js::from($showsOnlineUsers) }} && matches(userName)"
                                @if ($showsOnlineUsers) x-cloak @endif
                                class="flex items-center gap-3 rounded-xl px-2.5 py-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                            >
                                <div class="relative shrink-0">
                                    @if ($showsOnlineUsers)
                                        <flux:avatar circle color="teal" :name="$user->name" :initials="$user->initials()" />
                                        <span class="bg-online absolute right-0 bottom-0 size-3 rounded-full ring-2 ring-white dark:ring-zinc-900"></span>
                                    @else
                                        <flux:avatar circle :name="$user->name" :initials="$user->initials()" class="opacity-70" />
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                        @if ($showsOnlineUsers)
                                            <span x-show="$store.call.peerId === userId && $store.call.state !== 'idle'">{{ __('On a call with you') }}</span>
                                            <span x-show="! ($store.call.peerId === userId && $store.call.state !== 'idle')">{{ __('Available') }}</span>
                                        @else
                                            {{ __('Offline') }}
                                        @endif
                                    </div>
                                </div>

                                @if ($showsOnlineUsers)
                                    <flux:button
                                        variant="primary"
                                        icon="phone"
                                        square
                                        class="rounded-full!"
                                        :aria-label="__('Call :name', ['name' => $user->name])"
                                        x-bind:disabled="$store.call.state !== 'idle'"
                                        x-on:click="$store.call.start(userId, userName)"
                                    />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <flux:text x-show="search.trim() !== '' && countFor(true) + countFor(false) === 0" x-cloak class="px-3">
                {{ __('No one matches your search.') }}
            </flux:text>
        </div>
    @endif
</div>
