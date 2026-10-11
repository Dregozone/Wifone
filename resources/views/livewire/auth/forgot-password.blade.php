<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        @if (config('mail.default') === 'log')
            {{-- No real mailer is set up yet, so a reset email would never arrive. --}}
            <x-auth-header :title="__('Forgot password')" :description="__('Password resets are handled by hand for now')" />

            <flux:callout icon="chat-bubble-left-right" data-test="manual-password-reset-note">
                <flux:callout.text>
                    {{ __('Message Anders with the email address you signed up with, and your password will be reset for you.') }}
                </flux:callout.text>
            </flux:callout>
        @else
            <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

            <!-- Session Status -->
            <x-auth-session-status class="text-center" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autofocus
                    placeholder="email@example.com"
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                    {{ __('Email password reset link') }}
                </flux:button>
            </form>
        @endif

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
