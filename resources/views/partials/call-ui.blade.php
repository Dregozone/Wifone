{{-- Driven by the Alpine `call` store in resources/js/calls.js --}}
<div
    x-data="{
        initials(name) {
            return (name || '').split(' ').filter(Boolean).slice(0, 2).map((word) => word[0]).join('').toUpperCase();
        },
    }"
>
    {{-- Incoming call: full screen --}}
    <div
        id="incoming-call-modal"
        x-show="$store.call.state === 'incoming'"
        x-cloak
        x-transition.opacity.duration.200ms
        x-effect="if ($store.call.state === 'incoming') $nextTick(() => $refs.acceptButton.focus())"
        role="dialog"
        aria-modal="true"
        aria-labelledby="caller-name"
        class="fixed inset-0 z-50 flex items-center justify-center sm:bg-zinc-950/60 sm:p-6 sm:backdrop-blur-sm"
    >
        <div class="flex h-full w-full flex-col items-center bg-linear-to-b from-[#0f8487] to-[#0b5f66] px-6 pt-[max(4rem,env(safe-area-inset-top))] pb-[max(3rem,env(safe-area-inset-bottom))] text-white motion-safe:animate-[rise_.3s_ease-out] sm:h-auto sm:max-w-sm sm:rounded-[2rem] sm:pt-12 sm:pb-10 sm:shadow-2xl">
            <div class="relative mt-8 grid size-32 place-items-center sm:mt-2">
                <span class="absolute inset-0 animate-ring rounded-full bg-white/15 motion-reduce:animate-none"></span>
                <span class="absolute inset-0 animate-ring rounded-full bg-white/10 [animation-delay:1.2s] motion-reduce:animate-none"></span>
                <span class="relative grid size-32 place-items-center rounded-full bg-white/15 text-4xl font-semibold ring-8 ring-white/5" x-text="initials($store.call.peerName)"></span>
            </div>

            <h2 id="caller-name" class="mt-10 text-center text-3xl font-bold text-balance" x-text="$store.call.peerName"></h2>
            <p class="mt-1 text-teal-50/90">{{ __('Wifone audio call') }}</p>

            <div class="mt-auto flex w-full max-w-xs justify-between sm:mt-16">
                <div class="flex flex-col items-center gap-2 text-sm">
                    <button
                        type="button"
                        class="bg-hangup grid size-18 cursor-pointer place-items-center rounded-full shadow-lg transition hover:brightness-110 focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none active:scale-95"
                        aria-label="{{ __('Decline') }}"
                        x-on:click="$store.call.reject()"
                    >
                        <flux:icon.phone variant="solid" class="size-8 rotate-[135deg]" />
                    </button>
                    {{ __('Decline') }}
                </div>
                <div class="flex flex-col items-center gap-2 text-sm">
                    <button
                        type="button"
                        x-ref="acceptButton"
                        class="bg-online grid size-18 cursor-pointer place-items-center rounded-full shadow-lg transition hover:brightness-110 focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none active:scale-95"
                        aria-label="{{ __('Accept') }}"
                        x-on:click="$store.call.accept()"
                    >
                        <flux:icon.phone variant="solid" class="size-8" />
                    </button>
                    {{ __('Accept') }}
                </div>
            </div>
        </div>
    </div>

    {{-- In-call dock: calling / connecting / connected --}}
    <div
        id="in-call-ui"
        x-show="['outgoing', 'connecting', 'active', 'reconnecting'].includes($store.call.state)"
        x-cloak
        x-transition
        class="fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] left-1/2 z-40 w-[calc(100%-1.5rem)] max-w-md -translate-x-1/2 lg:bottom-6 lg:left-[calc(50%+8rem)]"
    >
        <div class="bg-ink flex items-center gap-3 rounded-3xl py-2.5 ps-4 pe-2.5 text-white shadow-2xl ring-1 ring-white/10">
            <div class="flex h-5 w-6 shrink-0 items-center justify-center gap-0.5" aria-hidden="true">
                @foreach ([0, 0.3, 0.15, 0.45, 0.6] as $delay)
                    <span
                        class="h-full w-0.5 origin-center rounded-full motion-reduce:animate-none"
                        style="animation-delay: {{ $delay }}s"
                        x-bind:class="$store.call.state === 'active' ? 'bg-teal-300 animate-wave' : 'bg-amber-300 scale-y-35 animate-pulse'"
                    ></span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1 leading-tight">
                <div id="in-call-with" class="truncate font-semibold" x-text="$store.call.peerName"></div>
                <div class="text-sm text-teal-100/85">
                    <span x-show="$store.call.state === 'outgoing'">{{ __('Calling…') }}</span>
                    <span x-show="$store.call.state === 'connecting'">{{ __('Connecting…') }}</span>
                    <span x-show="$store.call.state === 'reconnecting'" class="text-amber-300">{{ __('Reconnecting…') }}</span>
                    <span x-show="$store.call.state === 'active'"><span class="tabular-nums" x-text="$store.call.durationLabel()"></span> · {{ __('Connected') }}</span>
                </div>
            </div>

            <button
                type="button"
                class="bg-hangup grid size-11 shrink-0 cursor-pointer place-items-center rounded-full transition hover:brightness-110 focus-visible:ring-4 focus-visible:ring-white/50 focus-visible:outline-none"
                x-bind:aria-label="$store.call.state === 'outgoing' ? @js(__('Cancel call')) : @js(__('Hang up'))"
                x-on:click="$store.call.hangUp()"
            >
                <flux:icon.phone variant="solid" class="size-5 rotate-[135deg]" />
            </button>
        </div>
    </div>

    {{-- Call notices (declined, missed, failed...) --}}
    <div
        id="call-notice"
        x-show="$store.call.notice"
        x-cloak
        x-transition.opacity
        role="status"
        class="fixed top-[max(1rem,env(safe-area-inset-top))] left-1/2 z-50 w-[calc(100%-1.5rem)] max-w-md -translate-x-1/2 lg:left-[calc(50%+8rem)]"
    >
        <div class="bg-ink flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-white shadow-xl ring-1 ring-white/10">
            <flux:icon.information-circle variant="mini" class="size-5 shrink-0 text-teal-300" />
            <span x-text="$store.call.notice"></span>
        </div>
    </div>

    {{-- Screen reader announcements for call state (kept apart from the ticking timer) --}}
    <span
        class="sr-only"
        role="status"
        x-text="({
            incoming: @js(__('Incoming call')),
            outgoing: @js(__('Calling')),
            connecting: @js(__('Connecting')),
            active: @js(__('Call connected')),
            reconnecting: @js(__('Reconnecting')),
        })[$store.call.state] ?? ''"
    ></span>

    {{-- Remote Audio --}}
    <audio id="remote-audio" autoplay playsinline></audio>
</div>
