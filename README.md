# Wifone

A web-based, audio-only voice calling proof-of-concept built with Laravel, Reverb and WebRTC. Signed-in users can see who is online and call each other directly in the browser. The app can be installed on phones and desktops as a PWA.

## How it works

- **Presence**: every signed-in page joins the `online` presence channel, which drives the Online/Offline badges.
- **Call lifecycle** (server): `POST /calls`, `/calls/{call}/accept`, `/calls/{call}/reject` and `/calls/{call}/end` update the `calls` table and push `call.initiated`, `call.accepted`, `call.rejected` and `call.ended` events to the other person's private `calls.{userId}` channel. These events broadcast immediately, so no queue worker is needed.
- **Media negotiation** (peer to peer): the WebRTC offer, answer and ICE candidates travel as Echo *whispers* on a private `call.{id}` channel that only the two participants of a live call may join (see `CallPolicy::signal`).
- **Audio** flows directly between the browsers, using STUN, or a TURN relay when a direct path isn't possible.
- **Call history** (`/calls`) lists only calls the signed-in user made or received.

Key files: `app/Http/Controllers/CallController.php`, `app/Policies/CallPolicy.php`, `routes/channels.php`, `resources/js/calls.js` (call state machine), `resources/js/webrtc.js` (peer connection), `resources/views/partials/call-ui.blade.php`.

## Requirements

- PHP 8.4+, Composer, Node.js & npm
- HTTPS. Browsers only allow microphone access on secure origins, so plain `http://` won't work.

## Local development (Laravel Herd)

1. Install dependencies and set up the app:
   ```bash
   composer install
   npm install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```
2. Secure the site in Herd (`herd secure wifone`, or in the Herd UI) and set in `.env`:
   ```dotenv
   APP_URL=https://wifone.test
   REVERB_HOST="wifone.test"
   REVERB_PORT=8080
   REVERB_SCHEME=https
   ```
   Generate Reverb credentials with `php artisan reverb:install` if `REVERB_APP_*` are empty.
3. Run Reverb (using Herd's certificate) and Vite:
   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080 --hostname=wifone.test
   npm run dev
   ```
   Or run `composer run dev`, which starts both, plus a queue worker. Always browse via `https://wifone.test`. Don't use `php artisan serve`, because the session cookie is scoped to `wifone.test`.
4. Register two users and sign in as each in separate browsers (or a normal window plus a private window), then call from the dashboard.

## Embedding (the Life OS game)

Wifone can run inside a frame on sites you allow, so calls reach you while you use them. Set
`WIFONE_EMBED_ORIGINS` to their origins, separated by spaces (for the Life OS game:
`"http://localhost:5173 http://localhost:4173 http://localhost:4174"`). Framed, Wifone keeps a
session of its own in a partitioned cookie (`app/Http/Middleware/EmbeddedSession.php`), so you sign
in once inside the game, and `resources/js/embedBridge.js` tells the site around it when a call
comes in, connects or ends (and lets it answer, decline or hang up). Unset, nothing else may
frame Wifone.

## Deployment

See [docs/Deployment.md](docs/Deployment.md) for Laravel Forge and TURN setup.

## Tests

```bash
php artisan test --compact
```
