<div>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="{{ $user->id }}">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>

                    <flux:table.cell>
                        @if (in_array($user->id, $onlineUserIds))
                            <flux:badge color="green" size="sm" icon="signal">{{ __('Online') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm" icon="signal-slash">{{ __('Offline') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button
                            size="sm"
                            variant="primary"
                            :disabled="! in_array($user->id, $onlineUserIds)"
                            onclick="initiateCall({{ $user->id }}, '{{ e($user->name) }}')"
                        >
                            {{ __('Call') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
