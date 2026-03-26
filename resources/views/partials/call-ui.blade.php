{{-- Incoming Call Modal --}}
<div id="incoming-call-modal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800">
        <flux:heading size="lg" class="mb-2">{{ __('Incoming Call') }}</flux:heading>
        <flux:text class="mb-6">
            <span id="caller-name"></span> {{ __('is calling you') }}
        </flux:text>
        <div class="flex gap-3">
            <flux:button variant="primary" class="flex-1" onclick="acceptCall()">
                {{ __('Accept') }}
            </flux:button>
            <flux:button variant="danger" class="flex-1" onclick="rejectCall()">
                {{ __('Reject') }}
            </flux:button>
        </div>
    </div>
</div>

{{-- In-Call Overlay --}}
<div id="in-call-ui" style="display: none;" class="fixed bottom-6 left-1/2 z-40 -translate-x-1/2">
    <div class="flex items-center gap-4 rounded-xl bg-white px-6 py-4 shadow-xl dark:bg-zinc-800">
        <flux:icon.phone class="text-green-500" />
        <flux:text>
            {{ __('In call with') }} <span id="in-call-with" class="font-semibold"></span>
        </flux:text>
        <flux:button variant="danger" onclick="endCall()">
            {{ __('Hang Up') }}
        </flux:button>
    </div>
</div>

{{-- Remote Audio --}}
<audio id="remote-audio" autoplay playsinline></audio>
