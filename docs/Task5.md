# Task 5 – CallController & API Routes

## Goal
Create the `CallController` with seven action methods, each validating its input, broadcasting the appropriate event, and returning a JSON success response.

## Steps

1. Generate the controller:
   ```
   php artisan make:controller CallController --no-interaction
   ```

2. Implement the following methods:

   | Method            | Validates                            | Broadcasts            |
   |-------------------|--------------------------------------|-----------------------|
   | `initiateCall`    | `to_user_id` (int, exists:users)    | `CallInitiated`       |
   | `acceptCall`      | `to_user_id` (int, exists:users)    | `CallAccepted`        |
   | `rejectCall`      | `to_user_id` (int, exists:users)    | `CallRejected`        |
   | `endCall`         | `to_user_id` (int, exists:users)    | `CallEnded`           |
   | `sendOffer`       | `to_user_id`, `offer` (array/obj)   | `WebRTCOffer`         |
   | `sendAnswer`      | `to_user_id`, `answer` (array/obj)  | `WebRTCAnswer`        |
   | `sendCandidate`   | `to_user_id`, `candidate` (array)   | `WebRTCIceCandidate`  |

3. Each method:
   - Uses `$request->user()` as the sender
   - Optionally logs to the `calls` table (mark as pending on initiate, completed/rejected on end/reject)
   - Returns `response()->json(['status' => 'ok'])`

4. Register routes in `routes/web.php` under `auth` middleware in an `api`-style prefix:
   ```php
   Route::prefix('call')->middleware('auth')->group(function () {
       Route::post('initiate', [CallController::class, 'initiateCall'])->name('call.initiate');
       Route::post('accept',   [CallController::class, 'acceptCall'])->name('call.accept');
       Route::post('reject',   [CallController::class, 'rejectCall'])->name('call.reject');
       Route::post('end',      [CallController::class, 'endCall'])->name('call.end');
       Route::post('offer',    [CallController::class, 'sendOffer'])->name('call.offer');
       Route::post('answer',   [CallController::class, 'sendAnswer'])->name('call.answer');
       Route::post('candidate',[CallController::class, 'sendCandidate'])->name('call.candidate');
   });
   ```

## Acceptance Criteria
- All seven routes appear in `php artisan route:list --path=call`.
- Unauthenticated requests to any route return a redirect/401.
- Valid requests return `{"status":"ok"}`.

## Tests
Write `tests/Feature/CallControllerTest.php` (Pest) that:
- Uses `Event::fake()` to spy on broadcasts
- Logs in as a user and hits each endpoint with valid input
- Asserts the correct event is broadcast with the correct `toUserId`
- Asserts invalid/missing input returns 422
