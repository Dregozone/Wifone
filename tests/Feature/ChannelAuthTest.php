<?php

use App\Models\Call;
use App\Models\User;

test('authenticated user can authorise their own calls channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-calls.{$user->id}",
        ])
        ->assertOk();
});

test('authenticated user cannot authorise another users calls channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-calls.{$otherUser->id}",
        ])
        ->assertForbidden();
});

test('unauthenticated request to calls channel is rejected', function () {
    $user = User::factory()->create();

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-calls.{$user->id}",
    ])
        ->assertForbidden();
});

test('presence channel auth returns user identity payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-online',
        ])
        ->assertOk();

    $channelData = json_decode($response->json('channel_data'), true);

    expect($channelData['user_info'])->toBe([
        'id' => $user->id,
        'name' => $user->name,
    ]);
});

test('call participants can join the signalling channel of a live call', function () {
    $call = Call::factory()->active()->create();

    foreach ([$call->caller, $call->receiver] as $participant) {
        $this->actingAs($participant)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-call.{$call->id}",
            ])
            ->assertOk();
    }
});

test('strangers cannot join a call signalling channel', function () {
    $call = Call::factory()->active()->create();

    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-call.{$call->id}",
        ])
        ->assertForbidden();
});

test('participants cannot join the signalling channel of a finished call', function () {
    $call = Call::factory()->completed()->create();

    $this->actingAs($call->caller)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-call.{$call->id}",
        ])
        ->assertForbidden();
});
