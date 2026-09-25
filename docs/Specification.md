# Wifone – Web-Based Voice Calling (Proof of Concept)

Laravel + Reverb + WebRTC (audio only). Free tools only, except Flux Pro (already installed).

> Updated September 2026 to reflect the implemented design. The original specification had seven REST signalling endpoints and events. Those were replaced by whispers (see §3 and "Design decisions" below). `docs/Task1.md`–`Task10.md` record the original build plan and are kept for history only.

## 1. Project name

"Wifone": a play on *Wi-Fi* and *phone*.

## 2. Goal

A working proof-of-concept web app where two signed-in users can make real-time, audio-only voice calls in the browser using WebRTC. It must work across the internet (behind NAT) and be deployable on Laravel Forge. The backend is Laravel, real-time messaging uses Laravel Reverb, and everything is free except Flux Pro. The app is browser-only and installable as a PWA; there are no native apps and no calls to phone numbers.

## 3. Architecture

### Frontend
- Livewire 4 + Flux UI pages. Call state lives in Alpine stores (`resources/js/calls.js`), and WebRTC is wrapped in `resources/js/webrtc.js`.
- UI: user list with online status and Call buttons, incoming-call modal (Accept/Reject), calling/connecting/in-call bar with Cancel/Hang Up and a duration timer, notices (declined, missed, failed), and a hidden `<audio>` element for remote audio.
- The call UI is `@persist`ed so a call survives `wire:navigate` page changes.
- PWA: `public/manifest.json`, `public/sw.js` (offline page and asset caching; calls always need the network), and icons in `public/icons/`.

### Backend
- Authentication comes from the Livewire starter kit (Fortify).
- `CallController` handles the call lifecycle only: `store`, `accept`, `reject` and `end`. `CallPolicy` authorizes each action.
- `calls` table records every call, and the Call history page shows each user only their own calls.

### Real-time channels (Reverb)
| Channel | Type | Who may join | Used for |
|---------|------|--------------|----------|
| `online` | Presence | Any signed-in user | Online/offline badges |
| `calls.{userId}` | Private | That user | Server events: `call.initiated`, `call.accepted`, `call.rejected`, `call.ended` |
| `call.{callId}` | Private | The call's caller and receiver, while the call is ringing or active | Whispers: `offer`, `answer`, `ice` |

### Call flow
1. A clicks **Call**. A's browser asks for the microphone, sends `POST /calls` (status `ringing`), and subscribes to `call.{id}`. B receives `call.initiated` and sees the modal.
2. B clicks **Accept**. B's browser asks for the microphone and sends `POST /calls/{id}/accept` (status `active`), so A receives `call.accepted`. B subscribes to `call.{id}`, creates the WebRTC offer, and whispers it.
3. A answers via whisper. Both sides trickle ICE candidates via whisper, and early candidates are buffered until the remote description is set.
4. When the peer connection reaches `connected`, both sides show "In call".
5. Either side hangs up with `POST /calls/{id}/end` (status `completed`, or `missed` if still ringing), and the other side receives `call.ended`.
6. Other endings:
   - **Reject:** `rejected`.
   - **No answer within 30 seconds:** the caller cancels, recorded as `missed`.
   - **Busy callee:** the new call is rejected automatically.
   - **The other user goes offline or closes the tab:** the call shows "Reconnecting" for up to 20 seconds, then ends if they don't come back.
7. Resilience:
   - **Page reload mid-call:** the tab remembers its call in `sessionStorage`, looks it up with `GET /calls/{id}`, rejoins `call.{id}` and whispers `rejoin`. The other side then sends a fresh offer.
   - **Network failure after connecting:** the caller whispers `rejoin` to renegotiate, and both sides get a 20-second grace period.
   - **Several devices signed in:** all of the receiver's devices ring. `call.accepted` and `call.rejected` also go to the receiver's other devices (`toOthers()`), so they stop ringing.
   - **Abandoned calls:** browsers in a call send `POST /calls/{id}/heartbeat` every 20 seconds. `Call::expireStale()` marks ringing calls older than 45 seconds as missed, and active calls with no heartbeat for 60 seconds as completed. It runs on each new call and each history view, and every minute via the scheduler if that's enabled.

### STUN / TURN
- STUN: `stun:stun.l.google.com:19302`.
- TURN: optional, configured via `TURN_URL` (comma-separated), `TURN_USERNAME` and `TURN_CREDENTIAL`. Metered Open Relay is the recommended free provider. See `docs/Deployment.md`.

## 4. Functional requirements
- Only signed-in users can reach the call interface.
- Online presence is shown via the Reverb presence channel.
- A user can call an online user who isn't already in a call. The receiver can accept or reject.
- Audio only (`getUserMedia({ audio: true })`), using `RTCPeerConnection` with STUN plus optional TURN. The app handles `ontrack`, `onicecandidate` and `connectionstatechange`.
- Either user can hang up, and the caller can cancel while it's ringing.
- Each user can see their own call history (contact, direction, status, time, duration) and call back. Calls between other users are never visible.
- The app is installable as a PWA.
- There's deliberately no mute button: users mute with their hardware, which avoids "am I muted?" confusion.

## 5. Backend details

### Environment
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET
REVERB_HOST / REVERB_PORT / REVERB_SCHEME (+ VITE_ copies)
TURN_URL / TURN_USERNAME / TURN_CREDENTIAL (optional)
```

### Routes (auth + verified)
| Method | URI | Action | Who |
|--------|-----|--------|-----|
| GET | `/calls` | Call history (Livewire) | Any user; shows own calls only |
| POST | `/calls` | Start a call (`receiver_id`) | Any user, not to themselves |
| GET | `/calls/{call}` | Call details for resuming after a reload | Either participant |
| POST | `/calls/{call}/heartbeat` | Keep-alive while in a call | Either participant, while live |
| POST | `/calls/{call}/accept` | Accept | Receiver, while ringing |
| POST | `/calls/{call}/reject` | Reject | Receiver, while ringing |
| POST | `/calls/{call}/end` | Hang up / cancel (no-op if already ended) | Either participant |

### Events
`CallInitiated`, `CallAccepted`, `CallRejected` and `CallEnded` all implement `ShouldBroadcastNow`, so no queue worker is needed. Each is sent on the other participant's `calls.{userId}` channel.

## 6. Database

`calls`: `id`, `caller_id`, `receiver_id`, `started_at` (answered at), `ended_at`, `last_heartbeat_at`, `status` (`App\CallStatus`: `ringing`, `active`, `rejected`, `missed`, `completed`), and timestamps.

## 7. Deployment

Laravel Forge. Reverb runs either via the Forge toggle on a `ws.` subdomain, or, on a free `*.on-forge.com` domain, as a background process with Nginx forwarding `/app` and `/apps` on the same hostname. It also needs HTTPS, TURN credentials in the environment, and `npm run build` on deploy. Step by step: `docs/Deployment.md`.

## 8. Success criteria
- Two signed-in users see each other online.
- One can call the other; the other sees an incoming-call modal and can accept or reject.
- A WebRTC audio connection is established and both users hear each other clearly.
- Either user can hang up.
- It works reliably behind NAT using free TURN.
- Only free tools are used, apart from Flux Pro.

## Design decisions (and why they changed from the original spec)
- **Whispers instead of REST signalling:** the original POSTed every offer, answer and ICE candidate to Laravel, which re-broadcast it through a queued job. That needed a queue worker and added latency to dozens of messages per call. It also let anyone send connection data to anyone. Whispers go straight through Reverb, and only a live call's participants can join its channel.
- **`ShouldBroadcastNow`:** lifecycle events are few and time-critical, and it removes the need to run a queue worker.
- **The callee creates the offer:** the caller is already subscribed to the call channel while ringing, so the offer can't be sent before anyone is listening.
- **HTTPS everywhere, including locally (Herd):** browsers block the microphone on insecure origins.
