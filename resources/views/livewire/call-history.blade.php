<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Call history') }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">{{ __('Calls you have made and received') }}</flux:subheading>

    @if ($this->calls->isEmpty())
        <flux:text>{{ __('No calls yet.') }}</flux:text>
    @else
        <flux:table :paginate="$this->calls">
            <flux:table.columns>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Duration') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->calls as $call)
                    @php
                        $isOutgoing = $call->caller_id === auth()->id();
                        $contact = $isOutgoing ? $call->receiver : $call->caller;
                        $duration = $call->durationInSeconds();

                        [$statusLabel, $statusColor] = match ($call->status) {
                            \App\CallStatus::Completed => [__('Completed'), 'green'],
                            \App\CallStatus::Rejected => [__('Declined'), 'amber'],
                            \App\CallStatus::Missed => [$isOutgoing ? __('No answer') : __('Missed'), 'red'],
                            default => [__('In progress'), 'sky'],
                        };
                    @endphp

                    <flux:table.row :key="$call->id" x-data="{ userId: {{ $contact->id }}, userName: {{ Js::from($contact->name) }} }">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon
                                    :name="$isOutgoing ? 'arrow-up-right' : 'arrow-down-left'"
                                    variant="micro"
                                    class="text-zinc-400"
                                />
                                <span>{{ $contact->name }}</span>
                                <span class="sr-only">{{ $isOutgoing ? __('Outgoing') : __('Incoming') }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$statusColor" size="sm">{{ $statusLabel }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <span title="{{ $call->created_at->toDayDateTimeString() }}">{{ $call->created_at->diffForHumans() }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $duration === null ? '—' : sprintf('%d:%02d', intdiv($duration, 60), $duration % 60) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                icon="phone"
                                x-bind:disabled="! $store.presence.isOnline(userId) || $store.call.state !== 'idle'"
                                x-on:click="$store.call.start(userId, userName)"
                            >
                                {{ __('Call back') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
