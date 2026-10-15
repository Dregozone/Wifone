<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * How many wrong access codes one IP may try per hour.
     */
    public const int WRONG_ACCESS_CODES_PER_HOUR = 5;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort_unless(self::signUpIsOpen(), 403, __('Sign up is closed, no invitation code is active.'));

        $this->ensureAccessCodeIsRight((string) ($input['access_code'] ?? ''));

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Whether an access code is set, without which nobody may sign up.
     */
    public static function signUpIsOpen(): bool
    {
        return trim((string) config('wifone.access_code')) !== '';
    }

    /**
     * Refuse a wrong access code, and throttle an IP that keeps guessing.
     *
     * @throws ValidationException
     */
    private function ensureAccessCodeIsRight(string $accessCode): void
    {
        $throttleKey = 'register-access-code|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::WRONG_ACCESS_CODES_PER_HOUR)) {
            throw ValidationException::withMessages([
                'access_code' => __('Too many attempts, try again later'),
            ]);
        }

        $expected = mb_strtolower(trim((string) config('wifone.access_code')));

        if (! hash_equals($expected, mb_strtolower(trim($accessCode)))) {
            RateLimiter::hit($throttleKey, 3600);

            throw ValidationException::withMessages([
                'access_code' => __('That access code isn\'t right.'),
            ]);
        }
    }
}
