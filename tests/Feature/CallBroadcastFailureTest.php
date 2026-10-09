<?php

use App\CallStatus;
use App\Models\Call;
use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Exceptions;

/**
 * Point broadcasting at a connection that fails the way Reverb does when it is down
 * (connection refused) or answers with an error.
 */
function breakBroadcasting(Throwable $failure): void
{
    Broadcast::extend('failing', fn (): Broadcaster => new class($failure) extends Broadcaster
    {
        public function __construct(private Throwable $failure) {}

        public function auth($request): mixed
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result): mixed
        {
            return null;
        }

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw $this->failure;
        }
    });

    config(['broadcasting.connections.failing' => ['driver' => 'failing'], 'broadcasting.default' => 'failing']);
}

dataset('reverb failures', [
    'connection refused' => fn (): Throwable => new ConnectException('Connection refused', new PsrRequest('POST', 'http://127.0.0.1:6001')),
    'error reply' => fn (): Throwable => new BroadcastException('Pusher error: 500.'),
]);

it('tells the caller calling is unavailable when the receiver cannot be rung', function (Throwable $failure): void {
    Exceptions::fake();
    breakBroadcasting($failure);
    $caller = User::factory()->create();

    $this->actingAs($caller)
        ->postJson(route('calls.store'), ['receiver_id' => User::factory()->create()->id])
        ->assertServiceUnavailable()
        ->assertJson(['message' => 'Calling is unavailable right now. Please try again in a moment.']);

    expect(Call::where('caller_id', $caller->id)->sole()->status)->toBe(CallStatus::Missed);

    Exceptions::assertReported($failure::class);
})->with('reverb failures');

it('keeps the saved status when the other side cannot be told about it', function (string $routeName, Call $call, CallStatus $expectedStatus): void {
    Exceptions::fake();
    breakBroadcasting(new BroadcastException('Pusher error: 500.'));

    $this->actingAs($call->receiver)
        ->postJson(route($routeName, $call))
        ->assertOk();

    expect($call->fresh()->status)->toBe($expectedStatus);

    Exceptions::assertReported(BroadcastException::class);
})->with([
    'accept' => fn (): array => ['calls.accept', Call::factory()->create(), CallStatus::Active],
    'reject' => fn (): array => ['calls.reject', Call::factory()->create(), CallStatus::Rejected],
    'end' => fn (): array => ['calls.end', Call::factory()->active()->create(), CallStatus::Completed],
]);
