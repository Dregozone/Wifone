# Task 1 – Install & Configure Laravel Reverb (Broadcasting)

## Goal
Install Laravel Reverb and configure the broadcasting stack so the app can send and receive real-time events.

## Steps

1. Run the broadcasting installer:
   ```
   php artisan install:broadcasting
   ```
   Accept prompts to install Reverb and publish its config.

2. Confirm `config/broadcasting.php` exists and the `reverb` driver is present.

3. Install the Laravel Echo + Pusher-JS frontend packages:
   ```
   npm install --save-dev laravel-echo pusher-js
   ```

4. Ensure the following environment variables are present in `.env` (add if missing):
   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=local
   REVERB_APP_KEY=local
   REVERB_APP_SECRET=local
   REVERB_HOST=localhost
   REVERB_PORT=8080
   REVERB_SCHEME=http

   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"

   TURN_URL=turn:your-turn-url
   TURN_USERNAME=your-turn-username
   TURN_CREDENTIAL=your-turn-password
   ```
   The TURN vars are placeholders — leave blank for local testing (STUN-only will work on LAN).

5. Bootstrap Echo in `resources/js/app.js` (or a dedicated `bootstrap.js`) using the Reverb driver.

6. Run `npm run build` to verify the frontend compiles without errors.

7. Run `php artisan reverb:start` in a separate terminal to verify Reverb starts cleanly.

## Acceptance Criteria
- `php artisan reverb:start` runs without errors.
- `resources/js/app.js` initialises a `window.Echo` instance pointed at Reverb.
- `npm run build` succeeds.

## Tests
No automated tests required for this infrastructure task. Manual verification via `reverb:start` is sufficient.
