{{-- Driven by the Alpine `call` store in resources/js/calls.js --}}
<div x-data>
    {{-- Incoming Call Modal --}}
    <div
        id="incoming-call-modal"
        x-show="$store.call.state === 'incoming'"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800">
            <flux:heading size="lg" class="mb-2">{{ __('Incoming Call') }}</flux:heading>
            <flux:text class="mb-6">
                <span id="caller-name" class="font-semibold" x-text="$store.call.peerName"></span> {{ __('is calling you') }}
            </flux:text>
            <div class="flex gap-3">
                <flux:button variant="primary" icon="phone" class="flex-1" x-on:click="$store.call.accept()">
                    {{ __('Accept') }}
                </flux:button>
                <flux:button variant="danger" icon="phone-x-mark" class="flex-1" x-on:click="$store.call.reject()">
                    {{ __('Reject') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- In-Call Overlay: calling / connecting / connected --}}
    <div
        id="in-call-ui"
        x-show="['outgoing', 'connecting', 'active', 'reconnecting'].includes($store.call.state)"
        x-cloak
        class="fixed bottom-6 left-1/2 z-40 w-[calc(100%-2rem)] max-w-md -translate-x-1/2"
    >
        <div class="flex items-center gap-4 rounded-xl bg-white px-6 py-4 shadow-xl dark:bg-zinc-800">
            <flux:icon.phone
                class="shrink-0"
                x-bind:class="$store.call.state === 'active' ? 'text-green-500' : 'animate-pulse text-amber-500'"
            />
            <div class="min-w-0 flex-1">
                <flux:text>
                    <span x-show="$store.call.state === 'outgoing'">{{ __('Calling') }}</span>
                    <span x-show="$store.call.state === 'connecting'">{{ __('Connecting to') }}</span>
                    <span x-show="$store.call.state === 'reconnecting'">{{ __('Reconnecting to') }}</span>
                    <span x-show="$store.call.state === 'active'">{{ __('In call with') }}</span>
                    <span id="in-call-with" class="font-semibold" x-text="$store.call.peerName"></span>
                </flux:text>
                <flux:text size="sm" x-show="$store.call.state === 'active'" x-text="$store.call.durationLabel()"></flux:text>
            </div>
            <flux:button variant="danger" icon="phone-x-mark" x-on:click="$store.call.hangUp()">
                <span x-text="$store.call.state === 'outgoing' ? @js(__('Cancel')) : @js(__('Hang Up'))"></span>
            </flux:button>
        </div>
    </div>

    {{-- Call notices (declined, missed, failed...) --}}
    <div
        id="call-notice"
        x-show="$store.call.notice"
        x-cloak
        x-transition.opacity
        class="fixed top-6 left-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2"
    >
        <flux:callout variant="secondary" icon="information-circle">
            <flux:callout.text x-text="$store.call.notice"></flux:callout.text>
        </flux:callout>
    </div>

    {{-- Remote Audio --}}
    <audio id="remote-audio" autoplay playsinline></audio>
</div>
