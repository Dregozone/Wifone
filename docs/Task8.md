# Task 8 – JavaScript WebRTC Module

## Goal
Write a dedicated, self-contained JavaScript WebRTC module (`resources/js/webrtc.js`) that handles all peer-connection logic, microphone access, and ICE negotiation.

## File Structure
```
resources/js/
  webrtc.js       ← new file (this task)
  app.js          ← imports webrtc.js and echo.js (wired up in Task 9)
```

## Module API (exported functions)

```js
export async function startCall(toUserId)
// Requests microphone, creates RTCPeerConnection, adds local track.
// Creates an SDP offer and POSTs it to /call/offer.
// Stores remoteUserId for ICE candidate routing.

export async function handleOffer(fromUserId, offer)
// Sets remote description from the received SDP offer.
// Creates SDP answer and POSTs it to /call/answer.

export async function handleAnswer(answer)
// Sets remote description from the received SDP answer.

export async function addIceCandidate(candidate)
// Adds a received ICE candidate to the peer connection.

export function hangUp(toUserId)
// Closes the peer connection, stops mic tracks, POSTs to /call/end.
// Resets internal state.

export function getRemoteStream()
// Returns the MediaStream attached to the remote audio element (for debugging).
```

## Implementation Details

### ICE / STUN / TURN config
Read ICE server config from a global `window.iceServers` that the Blade layout injects:
```html
<script>
  window.iceServers = [
    { urls: 'stun:stun.l.google.com:19302' },
    @if(config('services.turn.url'))
    {
      urls: '{{ config("services.turn.url") }}',
      username: '{{ config("services.turn.username") }}',
      credential: '{{ config("services.turn.credential") }}'
    }
    @endif
  ];
</script>
```
Add the TURN values to `config/services.php`:
```php
'turn' => [
    'url'        => env('TURN_URL'),
    'username'   => env('TURN_USERNAME'),
    'credential' => env('TURN_CREDENTIAL'),
],
```

### RTCPeerConnection events to handle
- `onicecandidate` → POST to `/call/candidate`
- `ontrack` → set `srcObject` on `#remote-audio`
- `onconnectionstatechange` → log state; on `failed`/`closed` call `hangUp()`

### getUserMedia
```js
const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
```
Handle `NotAllowedError` gracefully with a user-facing alert.

## Acceptance Criteria
- Module exports all six functions.
- `startCall()` creates an offer and posts to `/call/offer`.
- `handleOffer()` creates an answer and posts to `/call/answer`.
- ICE candidates are posted to `/call/candidate`.
- Remote stream is attached to `#remote-audio`.

## Tests
This module is tested indirectly via the end-to-end call flow. No separate unit test required, but add a `tests/Feature/CallFlowTest.php` integration test in Task 10 that covers the signalling sequence.
