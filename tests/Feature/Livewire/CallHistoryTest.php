<?php

use App\Livewire\CallHistory;
use App\Models\Call;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication', function () {
    $this->get(route('calls.index'))->assertRedirect(route('login'));
});

it('renders the call history page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('calls.index'))
        ->assertOk()
        ->assertSeeLivewire(CallHistory::class)
        ->assertSee('No calls yet.');
});

it('shows calls the user made and received', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Friendly Frank']);
    $caller = User::factory()->create(['name' => 'Calling Carla']);

    $outgoing = Call::factory()->completed()->for($user, 'caller')->for($friend, 'receiver')->create();
    $incoming = Call::factory()->for($caller, 'caller')->for($user, 'receiver')->create(['status' => 'missed']);

    Livewire::actingAs($user)
        ->test(CallHistory::class)
        ->assertSee('Friendly Frank')
        ->assertSee('Calling Carla')
        ->assertSee('Completed')
        ->assertSee('Missed');

    expect(Call::involving($user)->pluck('id')->sort()->values()->all())->toBe([$outgoing->id, $incoming->id]);
});

it('never shows calls between other users', function () {
    $user = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Private Alice']);
    $bob = User::factory()->create(['name' => 'Private Bob']);

    Call::factory()->completed()->for($alice, 'caller')->for($bob, 'receiver')->create();

    Livewire::actingAs($user)
        ->test(CallHistory::class)
        ->assertDontSee('Private Alice')
        ->assertDontSee('Private Bob')
        ->assertSee('No calls yet.');
});

it('labels an unanswered outgoing call as no answer and shows the duration of completed calls', function () {
    $user = User::factory()->create();

    Call::factory()->for($user, 'caller')->create(['status' => 'missed']);
    Call::factory()->for($user, 'caller')->create([
        'status' => 'completed',
        'started_at' => now()->subSeconds(125),
        'ended_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(CallHistory::class)
        ->assertSee('No answer')
        ->assertSee('2:05');
});
