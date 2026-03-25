# Task 3 – Broadcast Events

## Goal
Create all seven signalling events that implement `ShouldBroadcast` and are dispatched over private Reverb channels.

## Events to Create

| Class               | Channel              | Payload fields                        |
|---------------------|----------------------|---------------------------------------|
| `CallInitiated`     | `calls.{toUserId}`   | `fromUserId`, `fromUserName`          |
| `CallAccepted`      | `calls.{toUserId}`   | `fromUserId`                          |
| `CallRejected`      | `calls.{toUserId}`   | `fromUserId`                          |
| `CallEnded`         | `calls.{toUserId}`   | `fromUserId`                          |
| `WebRTCOffer`       | `calls.{toUserId}`   | `fromUserId`, `offer` (SDP object)    |
| `WebRTCAnswer`      | `calls.{toUserId}`   | `fromUserId`, `answer` (SDP object)   |
| `WebRTCIceCandidate`| `calls.{toUserId}`   | `fromUserId`, `candidate` (ICE object)|

## Steps

1. Generate each event:
   ```
   php artisan make:event CallInitiated --no-interaction
   php artisan make:event CallAccepted --no-interaction
   php artisan make:event CallRejected --no-interaction
   php artisan make:event CallEnded --no-interaction
   php artisan make:event WebRTCOffer --no-interaction
   php artisan make:event WebRTCAnswer --no-interaction
   php artisan make:event WebRTCIceCandidate --no-interaction
   ```

2. For each event:
   - Implement `ShouldBroadcast` (not `ShouldBroadcastNow` — queue is fine)
   - Constructor accepts `int $toUserId` and the relevant payload fields
   - `broadcastOn()` returns `new PrivateChannel("calls.{$this->toUserId}")`
   - `broadcastAs()` returns the event name in `kebab-case` (e.g., `call.initiated`)
   - Keep all payload fields as `public` properties so they are automatically serialised

3. All events live in `app/Events/`.

## Acceptance Criteria
- All seven classes exist under `app/Events/`.
- Each implements `ShouldBroadcast` and returns the correct private channel.
- `php artisan event:list` shows all seven events.

## Tests
Write `tests/Feature/Events/BroadcastEventsTest.php` (Pest) that:
- Fakes broadcasting with `Event::fake()` or `Broadcast::fake()`
- Dispatches each event and asserts it was broadcast on the correct channel
