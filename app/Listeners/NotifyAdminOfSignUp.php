<?php

namespace App\Listeners;

use App\Http\Middleware\CaptureSignUpSource;
use App\Mail\NewUserSignedUp;
use App\Mail\SignUpBurst;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Emails the admin (ADMIN_NOTIFY_EMAIL) about each sign-up, after the response
 * has been sent, so signing up is never slowed down or broken by mail.
 *
 * In a wave of sign-ups (more than BURST_LIMIT within BURST_MINUTES) one
 * summary is sent instead, and nothing more until the window has passed.
 */
class NotifyAdminOfSignUp
{
    public const BURST_LIMIT = 5;

    public const BURST_MINUTES = 10;

    public const BURST_CACHE_KEY = 'sign-up-burst-notified';

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $adminEmail = config('wifone.admin_notify_email');

        if (blank($adminEmail) || ! $event->user instanceof User) {
            return;
        }

        $mail = $this->mailFor($event->user);

        if ($mail === null) {
            return;
        }

        defer(function () use ($adminEmail, $mail): void {
            try {
                Mail::to($adminEmail)->send($mail);
            } catch (Throwable $exception) {
                Log::error('Could not email the admin about a sign-up.', ['exception' => $exception]);
            }
        });
    }

    /**
     * The email to send about this sign-up, or null while a wave has already been reported.
     */
    protected function mailFor(User $user): ?Mailable
    {
        $totalUsers = User::count();
        $recentSignUps = User::where('created_at', '>=', now()->subMinutes(self::BURST_MINUTES))->count();

        if ($recentSignUps > self::BURST_LIMIT) {
            if (! Cache::add(self::BURST_CACHE_KEY, true, now()->addMinutes(self::BURST_MINUTES))) {
                return null;
            }

            return new SignUpBurst($recentSignUps, self::BURST_MINUTES, $totalUsers);
        }

        return new NewUserSignedUp(
            user: $user,
            signedUpAt: $user->created_at ?? now(),
            totalUsers: $totalUsers,
            country: request()->header('CF-IPCountry') ?: null,
            source: session()->get(CaptureSignUpSource::SESSION_KEY, []),
        );
    }
}
