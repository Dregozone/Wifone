<?php

use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('caller relation returns a User instance', function () {
    $call = Call::factory()->create();

    expect($call->caller)->toBeInstanceOf(User::class);
});

it('receiver relation returns a User instance', function () {
    $call = Call::factory()->create();

    expect($call->receiver)->toBeInstanceOf(User::class);
});
