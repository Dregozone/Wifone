<?php

use App\Http\Middleware\CaptureSignUpSource;
use App\Models\User;

test('a guest\'s outside referrer and utm parameters are remembered', function () {
    $this->withHeaders(['Referer' => 'https://news.example/post'])
        ->get(route('home', ['utm_source' => 'newsletter', 'utm_campaign' => 'launch', 'other' => 'ignored']))
        ->assertSessionHas(CaptureSignUpSource::SESSION_KEY, [
            'referrer' => 'https://news.example/post',
            'utm_source' => 'newsletter',
            'utm_campaign' => 'launch',
        ]);
});

test('only the first source is kept', function () {
    $this->get(route('home', ['utm_source' => 'first']));

    $this->get(route('register', ['utm_source' => 'second']))
        ->assertSessionHas(CaptureSignUpSource::SESSION_KEY, ['utm_source' => 'first']);
});

test('the site\'s own pages are not counted as a referrer', function () {
    $this->withHeaders(['Referer' => url('/')])
        ->get(route('register'))
        ->assertSessionMissing(CaptureSignUpSource::SESSION_KEY);
});

test('signed-in users are not tracked', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['utm_source' => 'newsletter']))
        ->assertSessionMissing(CaptureSignUpSource::SESSION_KEY);
});
