# Task 9 – Echo Listeners, Presence Channel & Full UI Wiring

## Goal
Connect everything: initialise Laravel Echo, listen on the private `calls.{userId}` channel for all signalling events, join the presence channel to track online users, and wire the UI (modal, in-call overlay, buttons) to the WebRTC module.

## File: `resources/js/app.js` updates

### 1. Bootstrap Echo
Ensure Echo is initialised with the Reverb driver (may already be done in Task 1):
```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### 2. Private channel listeners
Listen on `calls.{currentUserId}` for all seven event types. The currently-logged-in user's ID must be injected into the page — add this to the Blade layout:
```html
<script>window.authUserId = {{ auth()->id() ?? 'null' }};</script>
```

```js
import { startCall, handleOffer, handleAnswer, addIceCandidate, hangUp } from './webrtc.js';

if (window.authUserId) {
    window.Echo.private(`calls.${window.authUserId}`)

        .listen('.call.initiated', ({ fromUserId, fromUserName }) => {
            // Store caller info
            window.currentCallUserId = fromUserId;
            // Show incoming call modal
            document.getElementById('caller-name').textContent = fromUserName;
            showIncomingCallModal();
        })

        .listen('.call.accepted', ({ fromUserId }) => {
            hideIncomingCallModal();
            showInCallUi(fromUserId);
            startCall(fromUserId); // caller side begins WebRTC
        })

        .listen('.call.rejected', () => {
            hideIncomingCallModal();
            resetCallState();
            alert('Call was rejected.');
        })

        .listen('.call.ended', () => {
            hangUp(window.currentCallUserId);
            hideInCallUi();
            resetCallState();
        })

        .listen('.webrtc.offer', ({ fromUserId, offer }) => {
            window.currentCallUserId = fromUserId;
            showInCallUi(fromUserId);
            handleOffer(fromUserId, offer);
        })

        .listen('.webrtc.answer', ({ answer }) => {
            handleAnswer(answer);
        })

        .listen('.webrtc.ice-candidate', ({ candidate }) => {
            addIceCandidate(candidate);
        });
}
```

### 3. Presence channel — online user tracking
```js
window.Echo.join('presence-online')
    .here((users) => {
        updateOnlineUsers(users.map(u => u.id));
    })
    .joining((user) => {
        addOnlineUser(user.id);
    })
    .leaving((user) => {
        removeOnlineUser(user.id);
    });
```
`updateOnlineUsers` / `addOnlineUser` / `removeOnlineUser` should call a Livewire helper or dispatch a browser event to update the `OnlineUsers` Livewire component's `$onlineUserIds` array:
```js
function updateOnlineUsers(ids) {
    Livewire.dispatch('online-users-updated', { ids });
}
```
In the `OnlineUsers` Livewire component, listen for this event with `#[On('online-users-updated')]`.

### 4. Global UI helper functions (attach to `window` for Blade `onclick` bindings)
```js
window.initiateCall = function(toUserId, toUserName) { ... }  // POST /call/initiate then show waiting state
window.acceptCall   = function() { ... }     // POST /call/accept, hide modal, show in-call UI
window.rejectCall   = function() { ... }     // POST /call/reject, hide modal
window.endCall      = function() { ... }     // POST /call/end, hangUp(), hide in-call UI
```

### 5. Run `npm run build` after changes.

## Acceptance Criteria
- `window.Echo` is available in the browser console.
- Presence channel fires `here` with the current user's data.
- All event listeners are registered on the private channel.
- Online indicators in the user list update when users connect/disconnect.

## Tests
Covered by the integration test in Task 10. No additional unit tests needed here.
