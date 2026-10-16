<section class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl" level="1" class="mb-6 text-2xl! font-bold!">{{ __('Invites') }}</flux:heading>

    @if ($this->schedules->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl bg-white px-6 py-12 text-center ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <flux:icon.calendar-days class="size-8 text-zinc-400" />
            <flux:heading>{{ __('No scheduled calls.') }}</flux:heading>
            <flux:text>{{ __('Use Schedule a call on someone\'s card in Contacts to agree a time to talk.') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @foreach ([
                'answer' => ['title' => __('Waiting for your answer'), 'entries' => $this->invitesToAnswer],
                'upcoming' => ['title' => __('Upcoming calls'), 'entries' => $this->upcomingCalls],
                'sent' => ['title' => __('Sent invites'), 'entries' => $this->invitesSent],
            ] as $group => $section)
                @continue($section['entries']->isEmpty())

                <section>
                    <h2 class="px-3 pb-2 text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                        {{ $section['title'] }}
                        <span class="tabular-nums">· {{ $section['entries']->count() }}</span>
                    </h2>

                    <ul class="flex flex-col gap-0.5 rounded-2xl motion-safe:animate-[rise_.35s_ease-out] bg-white p-1.5 shadow-xs ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
                        @foreach ($section['entries'] as ['schedule' => $schedule, 'next' => $next])
                            @php
                                $contact = $schedule->caller_id === auth()->id() ? $schedule->callee : $schedule->caller;
                                $repeatSummary = match ($schedule->repeat) {
                                    \App\CallScheduleRepeat::Weekdays => collect($schedule->weekdays)
                                        ->map(fn (int $weekday): string => now()->startOfWeek()->addDays($weekday - 1)->translatedFormat('D'))
                                        ->join(', '),
                                    default => $schedule->repeat->label(),
                                };
                                $opensAt = $next->subMinutes(\App\Models\CallSchedule::WINDOW_OPENS_MINUTES_BEFORE);
                                $closesAt = $next->addMinutes(\App\Models\CallSchedule::WINDOW_CLOSES_MINUTES_AFTER);
                            @endphp

                            <li
                                wire:key="{{ $group }}-schedule-{{ $schedule->id }}-{{ $next->getTimestamp() }}"
                                x-data="{
                                    scheduleId: {{ $schedule->id }},
                                    userId: {{ $contact->id }},
                                    userName: {{ Js::from($contact->name) }},
                                    opensAt: {{ $opensAt->getTimestampMs() }},
                                    closesAt: {{ $closesAt->getTimestampMs() }},
                                    now: Date.now(),
                                    timer: null,
                                    init() {
                                        this.timer = setInterval(() => {
                                            this.now = Date.now();

                                            // The slot is over: show the next one.
                                            if (this.now > this.closesAt) {
                                                clearInterval(this.timer);
                                                this.$wire.refreshSchedules();
                                            }
                                        }, 15000);
                                    },
                                    destroy() {
                                        clearInterval(this.timer);
                                    },
                                    get isInWindow() {
                                        return this.now >= this.opensAt && this.now <= this.closesAt;
                                    },
                                }"
                                class="flex flex-wrap items-center gap-3 rounded-xl px-2.5 py-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                            >
                                <flux:avatar circle color="teal" :name="$contact->name" :initials="$contact->initials()" class="shrink-0" />

                                <div class="min-w-0 flex-1 basis-40">
                                    <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $contact->name }}</div>
                                    <div class="flex flex-wrap items-center gap-x-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        <span class="sr-only">{{ __('Next call') }}</span>
                                        <time
                                            datetime="{{ $next->toIso8601String() }}"
                                            x-text="$localTime($el.dateTime)"
                                            class="tabular-nums"
                                        >{{ $next->setTimezone($schedule->timezone)->format('D j M, H:i') }}</time>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $repeatSummary }}</span>
                                        @if ($schedule->until)
                                            <span>{{ __('until :date', ['date' => $schedule->until->translatedFormat('j M Y')]) }}</span>
                                        @endif
                                    </div>
                                    @if ($group === 'sent')
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Waiting for :name to answer', ['name' => $contact->name]) }}</div>
                                    @endif
                                </div>

                                <div class="ms-auto flex items-center gap-1">
                                    @if ($group === 'answer')
                                        <flux:button size="sm" variant="filled" wire:click="decline({{ $schedule->id }})" :aria-label="__('Decline the invite from :name', ['name' => $contact->name])">{{ __('Decline') }}</flux:button>
                                        <flux:button size="sm" variant="primary" wire:click="accept({{ $schedule->id }})" :aria-label="__('Accept the invite from :name', ['name' => $contact->name])">{{ __('Accept') }}</flux:button>
                                    @elseif ($group === 'upcoming')
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            icon="phone"
                                            x-bind:disabled="! isInWindow || ! $store.presence.isOnline(userId) || $store.call.state !== 'idle' || ! $store.presence.connected"
                                            x-on:click="$store.call.start(userId, userName, scheduleId)"
                                        >{{ __('Start scheduled call') }}</flux:button>
                                    @endif

                                    @if ($group !== 'answer')
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="sm" variant="subtle" icon="ellipsis-vertical" square :aria-label="__('More options for the call with :name', ['name' => $contact->name])" />

                                            <flux:menu>
                                                @if ($schedule->repeats())
                                                    <flux:menu.item icon="forward" wire:click="skip({{ $schedule->id }}, '{{ $next->setTimezone($schedule->timezone)->toDateString() }}')">
                                                        {{ __('Skip the next call') }}
                                                    </flux:menu.item>
                                                @endif
                                                <flux:menu.item
                                                    icon="x-mark"
                                                    variant="danger"
                                                    wire:click="cancel({{ $schedule->id }})"
                                                    wire:confirm="{{ $schedule->repeats() ? __('Cancel every call in this series?') : __('Cancel this call?') }}"
                                                >
                                                    {{ $group === 'sent' ? __('Withdraw invite') : ($schedule->repeats() ? __('Cancel series') : __('Cancel call')) }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>

                                @if ($group === 'upcoming')
                                    <flux:text size="sm" class="w-full ps-13" x-show="isInWindow && ! $store.presence.isOnline(userId)" x-cloak>
                                        {{ __(':name is offline. You can start the call once they come online.', ['name' => $contact->name]) }}
                                    </flux:text>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</section>
