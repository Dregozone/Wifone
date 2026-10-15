<?php

use App\Models\User;

it('updates activity at most once per hour', function () {
    $this->travelTo('2026-10-13 10:00:00');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $user->refresh();
    expect($user->last_active_at->toDateTimeString())->toBe('2026-10-13 10:00:00');

    $this->travel(59)->minutes();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $user->refresh();
    expect($user->last_active_at->toDateTimeString())->toBe('2026-10-13 10:00:00');

    $this->travel(1)->minutes();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $user->refresh();
    expect($user->last_active_at->toDateTimeString())->toBe('2026-10-13 11:00:00');
});
