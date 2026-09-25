<div>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}" x-data="{ userId: {{ $user->id }}, userName: {{ Js::from($user->name) }} }">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>

                    <flux:table.cell>
                        <span x-show="$store.presence.isOnline(userId)" x-cloak>
                            <flux:badge color="green" size="sm" icon="signal">{{ __('Online') }}</flux:badge>
                        </span>
                        <span x-show="! $store.presence.isOnline(userId)">
                            <flux:badge color="zinc" size="sm" icon="signal-slash">{{ __('Offline') }}</flux:badge>
                        </span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button
                            size="sm"
                            variant="primary"
                            icon="phone"
                            x-bind:disabled="! $store.presence.isOnline(userId) || $store.call.state !== 'idle'"
                            x-on:click="$store.call.start(userId, userName)"
                        >
                            {{ __('Call') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
