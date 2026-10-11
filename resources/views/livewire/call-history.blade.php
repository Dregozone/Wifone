<section class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl" level="1" class="mb-6 text-2xl! font-bold!">{{ __('Recents') }}</flux:heading>

    @if ($this->calls->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-white px-6 py-12 text-center ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <flux:icon.clock class="size-8 text-zinc-400" />
            <flux:heading>{{ __('No calls yet.') }}</flux:heading>
            <flux:text>{{ __('Calls you make and receive will be listed here.') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @foreach ($this->calls->groupBy(fn ($call) => $call->created_at->toDateString()) as $calls)
                @php
                    $day = $calls->first()->created_at;
                    $dayLabel = match (true) {
                        $day->isToday() => __('Today'),
                        $day->isYesterday() => __('Yesterday'),
                        default => $day->translatedFormat($day->isCurrentYear() ? 'l j F' : 'j F Y'),
                    };
                @endphp

                <section>
                    <h2 class="px-3 pb-2 text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">{{ $dayLabel }}</h2>

                    <ul class="flex flex-col gap-0.5 rounded-2xl motion-safe:animate-[rise_.35s_ease-out] bg-white p-1.5 shadow-xs ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
                        @foreach ($calls as $call)
                            @php
                                $isOutgoing = $call->caller_id === auth()->id();
                                $contact = $isOutgoing ? $call->receiver : $call->caller;
                                $duration = $call->durationInSeconds();
                                $isMissedByMe = ! $isOutgoing && $call->status === \App\CallStatus::Missed;

                                $statusLabel = match ($call->status) {
                                    \App\CallStatus::Completed => __('Completed'),
                                    \App\CallStatus::Rejected => __('Declined'),
                                    \App\CallStatus::Missed => $isOutgoing ? __('No answer') : __('Missed'),
                                    default => __('In progress'),
                                };
                            @endphp

                            <li
                                wire:key="call-{{ $call->id }}"
                                x-data="{ userId: {{ $contact->id }}, userName: {{ Js::from($contact->name) }} }"
                                class="flex items-center gap-3 rounded-xl px-2.5 py-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                            >
                                <flux:avatar circle color="teal" :name="$contact->name" :initials="$contact->initials()" class="shrink-0" />

                                <div class="min-w-0 flex-1">
                                    <div @class([
                                        'truncate font-semibold',
                                        'text-hangup dark:text-red-400' => $isMissedByMe,
                                        'text-zinc-900 dark:text-white' => ! $isMissedByMe,
                                    ])>{{ $contact->name }}</div>
                                    <div class="flex items-center gap-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        <flux:icon :name="$isOutgoing ? 'arrow-up-right' : 'arrow-down-left'" variant="micro" class="size-3.5 shrink-0" />
                                        <span class="sr-only">{{ $isOutgoing ? __('Outgoing') : __('Incoming') }}</span>
                                        <span class="truncate">{{ $statusLabel }}</span>
                                        <span aria-hidden="true">·</span>
                                        <time datetime="{{ $call->created_at->toIso8601String() }}" title="{{ $call->created_at->toDayDateTimeString() }}" class="shrink-0 tabular-nums">{{ $call->created_at->format('H:i') }}</time>
                                    </div>
                                </div>

                                @if ($duration !== null)
                                    <span class="text-sm text-zinc-500 tabular-nums dark:text-zinc-400">{{ sprintf('%d:%02d', intdiv($duration, 60), $duration % 60) }}</span>
                                @endif

                                <flux:button
                                    variant="subtle"
                                    icon="phone"
                                    square
                                    class="rounded-full! text-accent-content!"
                                    :aria-label="__('Call :name back', ['name' => $contact->name])"
                                    x-bind:disabled="! $store.presence.isOnline(userId) || $store.call.state !== 'idle' || ! $store.presence.connected"
                                    x-on:click="$store.call.start(userId, userName)"
                                />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @if ($this->calls->hasPages())
                <flux:pagination :paginator="$this->calls" />
            @endif
        </div>
    @endif
</section>
