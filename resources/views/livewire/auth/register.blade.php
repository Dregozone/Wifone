<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        @if (\App\Actions\Fortify\CreateNewUser::signUpIsOpen())
            <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

            <!-- Session Status -->
            <x-auth-session-status class="text-center" :status="session('status')" />

            <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
                @csrf
                <!-- Name -->
                <flux:input
                    name="name"
                    :label="__('Name')"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Full name')"
                />

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Access Code -->
                <flux:input
                    name="access_code"
                    :label="__('Access code')"
                    type="text"
                    required
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    :placeholder="__('The code you were given')"
                />

                <!-- Password -->
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    :description:trailing="app()->isProduction() ? __('At least 12 characters, with upper and lower case letters, a number and a symbol. Passwords found in known data breaches are not allowed.') : null"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    viewable
                />

                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                        {{ __('Create account') }}
                    </flux:button>
                </div>
            </form>
        @else
            <x-auth-header :title="__('Create an account')" :description="__('Sign up is by invitation only')" />

            <flux:callout icon="lock-closed" data-test="sign-up-closed-note">
                <flux:callout.text>
                    {{ __('Sign up is closed, no invitation code is active.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
