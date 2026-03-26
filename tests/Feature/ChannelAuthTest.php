<?php

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
