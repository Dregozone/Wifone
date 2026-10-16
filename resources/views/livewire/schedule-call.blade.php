<div
    x-init="$wire.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'"
    x-on:call-scheduled.window="$store.call.flash($event.detail.message)"
>
    <flux:modal name="schedule-call" focusable class="w-full max-w-md">
        <form wire:submit="send" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Schedule a call') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Pick a time to call :name. They can accept or decline.', ['name' => $calleeName]) }}</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="date" name="date" :label="__('Date')" required />
                <flux:input type="time" wire:model="time" name="time" :label="__('Time')" required />
            </div>

            <flux:select wire:model="repeat" name="repeat" :label="__('Repeat')">
                @foreach ($repeats as $repeatOption)
                    <flux:select.option :value="$repeatOption->value">{{ $repeatOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div x-show="$wire.repeat === 'weekdays'" x-cloak>
                <flux:checkbox.group wire:model="weekdays" :label="__('On')">
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ([1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')] as $weekday => $weekdayLabel)
                            <flux:checkbox :value="$weekday" :label="$weekdayLabel" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
            </div>

            <div x-show="$wire.repeat !== 'none'" x-cloak>
                <flux:input type="date" wire:model="until" name="until" :label="__('End date')" :description="__('Optional. Leave empty to keep repeating.')" />
            </div>

            <flux:text size="sm">{{ __('Times are in your time zone') }} (<span x-text="$wire.timezone"></span>).</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" icon="paper-airplane">{{ __('Send invite') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
