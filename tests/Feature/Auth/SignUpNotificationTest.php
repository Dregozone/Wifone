<?php

use App\Http\Middleware\CaptureSignUpSource;
use App\Mail\NewUserSignedUp;
use App\Mail\SignUpBurst;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());

    config(['wifone.admin_notify_email' => 'admin@example.com']);
});

function signUp(string $email = 'test@example.com', array $headers = []): TestResponse
{
    return test()->withHeaders($headers)->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

test('the admin is emailed when someone signs up', function () {
    Mail::fake();
    User::factory()->count(2)->create(['created_at' => now()->subDay()]);

    signUp(headers: ['CF-IPCountry' => 'GB'])->assertRedirect(route('dashboard', absolute: false));

    Mail::assertSent(NewUserSignedUp::class, function (NewUserSignedUp $mail): bool {
        return $mail->hasTo('admin@example.com')
            && $mail->hasSubject('New Wifone sign-up: John Doe')
            && $mail->user->email === 'test@example.com'
            && $mail->totalUsers === 3
            && $mail->country === 'GB';
    });
    Mail::assertNotSent(SignUpBurst::class);
});

test('the sign-up email says where they came from and when, in London time', function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 7, 1)->setTime(11, 30));

    $this->withSession([CaptureSignUpSource::SESSION_KEY => ['referrer' => 'https://news.example/post', 'utm_source' => 'newsletter']]);
    signUp(headers: ['CF-IPCountry' => 'FR']);

    Mail::assertSent(NewUserSignedUp::class, function (NewUserSignedUp $mail): bool {
        $mail->assertSeeInHtml('test@example.com')
            ->assertSeeInHtml('Wed 1 Jul 2026, 12:30 BST')
            ->assertSeeInHtml('FR')
            ->assertSeeInHtml('https://news.example/post')
            ->assertSeeInHtml('newsletter');

        return true;
    });
});

test('no email is sent without an admin address', function () {
    Mail::fake();
    config(['wifone.admin_notify_email' => null]);

    signUp()->assertRedirect(route('dashboard', absolute: false));

    Mail::assertNothingSent();
});

test('a mail failure does not break sign-up', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Resend is down'));
    Log::shouldReceive('error')->once();

    signUp()->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('a wave of sign-ups becomes a single summary', function () {
    Mail::fake();

    foreach (range(1, 12) as $number) {
        signUp("user{$number}@example.com");
        auth()->logout();
    }

    Mail::assertSent(NewUserSignedUp::class, 5);
    Mail::assertSent(SignUpBurst::class, 1);
    Mail::assertSent(SignUpBurst::class, fn (SignUpBurst $mail): bool => $mail->hasTo('admin@example.com')
        && $mail->recentSignUps === 6
        && $mail->hasSubject('6 Wifone sign-ups in the last 10 minutes'));
});

test('sign-ups are emailed one by one again once the wave has passed', function () {
    Mail::fake();
    User::factory()->count(6)->create(['created_at' => now()->subMinutes(11)]);

    signUp();

    Mail::assertSent(NewUserSignedUp::class, 1);
    Mail::assertNotSent(SignUpBurst::class);
});
