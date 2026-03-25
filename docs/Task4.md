# Task 4 – Broadcast Channel Authorisation

## Goal
Define and authorise the private and presence channels used for signalling and user presence.

## Channels

| Channel                  | Type     | Authorisation rule                                             |
|--------------------------|----------|----------------------------------------------------------------|
| `calls.{userId}`         | Private  | Authenticated user's ID must match `{userId}`                 |
| `presence-online`        | Presence | Any authenticated user may join; return user identity payload |

## Steps

1. Check whether `routes/channels.php` already exists (it may be created by `install:broadcasting`). If not, create it.

2. Register the **private** channel:
   ```php
   Broadcast::channel('calls.{userId}', function (User $user, int $userId) {
       return $user->id === $userId;
   });
   ```

3. Register the **presence** channel (for the online-users list):
   ```php
   Broadcast::channel('presence-online', function (User $user) {
       return ['id' => $user->id, 'name' => $user->name];
   });
   ```

4. Ensure `routes/channels.php` is loaded — it should be auto-loaded by `bootstrap/app.php` via `withRouting(channels: __DIR__.'/../routes/channels.php')` or equivalent.

## Acceptance Criteria
- An authenticated user can subscribe to their own `calls.{userId}` channel.
- An unauthenticated request to the channel auth endpoint returns 403.
- Presence channel auth returns the user identity array.

## Tests
Write `tests/Feature/ChannelAuthTest.php` (Pest) that:
- Asserts an authenticated user can authorise `calls.{their own id}`
- Asserts a user cannot authorise `calls.{a different user's id}`
- Asserts an unauthenticated request is rejected
