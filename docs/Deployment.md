# Deploying Wifone

This covers two things:

- a free TURN relay, so calls connect across the internet;
- deployment to Laravel Forge with Reverb.

Replace `wifone.example.com` with your real domain throughout.

---

## 1. TURN relay (Metered Open Relay, free)

**Why:** WebRTC tries to connect browsers directly (using STUN). On many mobile networks and corporate or strict home routers, a direct path isn't possible. The audio then has to be relayed through a TURN server. Without one, roughly 1 in 5 internet calls fail with *"The connection failed. A TURN server may be needed on this network."*

Open Relay gives 20 GB of relayed traffic free each month. An audio call uses roughly 50 MB per hour, and only when relaying is actually needed.

1. Sign up at <https://dashboard.metered.ca/signup?tool=turnserver>. You'll be asked to create an app name, e.g. `wifone`, which gives you `wifone.metered.live`.
2. In the Metered dashboard, open **TURN Server**, create a credential, and view its ICE servers config. You'll get a list of URLs similar to:
   ```
   stun:stun.relay.metered.ca:80
   turn:global.relay.metered.ca:80
   turn:global.relay.metered.ca:80?transport=tcp
   turn:global.relay.metered.ca:443
   turns:global.relay.metered.ca:443?transport=tcp
   ```
   plus a **username** and **credential**. The exact hostnames in your dashboard take precedence over this example.
3. Add them to the environment: `.env` locally, or the Forge **Environment** tab. List every `turn:`/`turns:` URL, comma-separated. Include the TCP/443 ones, because they get through the strictest firewalls.
   ```dotenv
   TURN_URL="turn:global.relay.metered.ca:80,turn:global.relay.metered.ca:80?transport=tcp,turn:global.relay.metered.ca:443,turns:global.relay.metered.ca:443?transport=tcp"
   TURN_USERNAME=your-username
   TURN_CREDENTIAL=your-credential
   ```
4. If config is cached (it is on Forge after a deploy), run `php artisan config:cache`, then reload the page.

**How to check it's used:** open `chrome://webrtc-internals` during a call. Candidates of type `relay` mean TURN is working. To force a relayed call for testing, put one device on mobile data.

> Note: these credentials are sent to signed-in users' browsers. That's normal for WebRTC and fine for a prototype. For production, switch to Metered's credentials API (`https://<app>.metered.live/api/v1/turn/credentials?apiKey=...`) behind a small authenticated Laravel endpoint, so each session gets short-lived credentials.

---

## 2. Laravel Forge

### 2.1 Server and site

1. **Create a server** in Forge as an *App Server*. The smallest size is plenty for a prototype. Pick PHP 8.4 and a database (MySQL or Postgres, or keep SQLite if you prefer).
2. **Choose how Reverb is reached.** Browsers connect to Reverb over secure WebSockets (`wss://`) on port 443.
   - **Option A – free `*.on-forge.com` domain (single hostname):** Reverb shares the site's hostname. Nginx forwards the `/app` and `/apps` paths to Reverb. No extra DNS is needed. Follow the **A** steps below.
   - **Option B – your own domain:** add A records for `wifone.example.com` *and* `ws.wifone.example.com`, and use Forge's Reverb toggle. Follow the **B** steps below.
3. **Create a site** for your hostname and connect the GitHub repo `Dregozone/Wifone`, branch `main`.
4. Because Flux Pro is a private Composer package, add its credentials before the first deploy. On the server, run `composer config --global http-basic.composer.fluxui.dev <email> <license-key>`. Alternatively, add them to the site's Composer credentials in Forge if your Forge UI offers that.

### 2.2 Environment

In the site's **Environment** tab, start from `.env.example` and set at least the following. The `REVERB_*` values come from your local `.env`, or generate new ones with `php artisan reverb:install`.

```dotenv
APP_NAME=Wifone
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wifone.example.com

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
# Option A (on-forge.com): the site's own hostname. Option B: "ws.wifone.example.com"
REVERB_HOST="your-site.on-forge.com"
REVERB_PORT=443
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=6001
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

TURN_URL="..."
TURN_USERNAME=...
TURN_CREDENTIAL=...
```

> `VITE_*` values are compiled into the JavaScript at build time. Whenever you change any `REVERB_*` value, **redeploy** so `npm run build` runs again.

### 2.3 SSL

HTTPS is mandatory, because browsers block the microphone on plain HTTP.

- **A (on-forge.com):** Forge's free domains come with HTTPS. If the site isn't on `https://` yet, request a certificate in the site's **SSL** tab.
- **B (own domain):** in the **SSL** tab, request a Let's Encrypt certificate covering **both** `wifone.example.com` and `ws.wifone.example.com`.

### 2.4 Run Reverb

#### Option A – on-forge.com (single hostname): background process + Nginx paths

Forge's Reverb toggle needs a separate hostname, so set Reverb up by hand. There are two parts.

**1. Background process (the Reverb command).** In Forge open **Server → Background processes** (called *Daemons* in older Forge) and create a new process:

| Field | Value |
|-------|-------|
| Command | `php8.4 artisan reverb:start --host=127.0.0.1 --port=6001 --no-interaction` |
| Directory | `/home/forge/<your-site>.on-forge.com/current` with zero-downtime deployments, otherwise `/home/forge/<your-site>.on-forge.com` |
| User | `forge` |
| Processes | `1` |

Use the site's real folder name; the site's overview shows its path. Forge's Supervisor keeps the command running and restarts it if it crashes. `php artisan reverb:restart` in the deploy script (§2.5) makes it pick up new code after each deploy.

**2. Nginx: forward WebSocket paths to Reverb.** Open the site's **Nginx configuration** editor (site → *Nginx* / *Edit Nginx Configuration*). Inside the `server { ... }` block that has `listen 443`, paste this **above** the existing `location / {` block, then save:

```nginx
    # Laravel Reverb (WebSockets) on the same hostname
    location ~ ^/apps?/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_read_timeout 120s;
        proxy_pass http://127.0.0.1:6001;
    }
```

`/app/...` carries browser WebSocket connections, and `/apps/...` is where Laravel publishes events to Reverb. Wifone has no routes of its own under those paths, so nothing clashes.

Environment for this option: `REVERB_HOST` is the site's own hostname, `REVERB_PORT=443`, `REVERB_SCHEME=https`, and `REVERB_SERVER_HOST=127.0.0.1` / `REVERB_SERVER_PORT=6001` (see §2.2). Port 6001 is used rather than 8080 so it is unlikely to clash with other sites or tools on a shared server; any free port works as long as the command, the Nginx block and `REVERB_SERVER_PORT` all match.

**Check:** after deploying, `https://<your-site>.on-forge.com/apps/1/channels` should return a short plain-text Reverb reply ("No matching application for ID [1].") rather than a styled Laravel 404 page. A plain browser visit to `/app/<key>` returning "Internal server error." is also Reverb (it expects a WebSocket there), so that is fine too.

#### Option B – own domain: Forge's Reverb toggle

1. Open the site's **Overview** tab and find the **Application** panel.
2. Turn on the **Laravel Reverb** toggle and fill in:
   - **Public hostname:** `ws.wifone.example.com` (Forge's default)
   - **Port:** `8080`
   - **Maximum concurrent connections:** the default is fine
3. Forge will then:
   - create a daemon that runs `php artisan reverb:start` on port 8080, restarted automatically if it crashes;
   - add an Nginx server block that proxies `wss://ws.wifone.example.com` (port 443) to that port, using the SSL certificate above;
   - append `php artisan reverb:restart` to the deploy script.

If the toggle is missing, use Option A's background process and Nginx block instead. They work for a custom domain too.

### 2.5 Deploy script

Make sure the site's deploy script contains the following (Forge's Laravel default plus the frontend build and the Reverb restart):

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

$FORGE_PHP artisan reverb:restart
```

If your site doesn't use zero-downtime deployments, drop the `$CREATE_RELEASE()`/`$ACTIVATE_RELEASE()` lines and `cd $FORGE_SITE_PATH` instead.

**No queue worker is required**, because call events broadcast immediately. The **Laravel Scheduler** toggle is optional. With it on, calls abandoned by crashed browsers are tidied every minute. Without it, they are tidied whenever someone starts a call or opens their call history.

### 2.6 Smoke test

1. Deploy, then open `https://wifone.example.com` and register two users.
2. Sign in on a laptop, and on a phone using **mobile data** (not your Wi-Fi) so the call has to cross the internet.
3. Check both show as online, call, accept, talk both ways, and hang up.
4. Check **Call history** on both devices.
5. On the phone, use *Add to Home Screen* (iOS Safari) or *Install app* (Android Chrome) to install the PWA.

If the online badges never turn green, the WebSocket isn't connecting. Check in this order:
- Option A: the Nginx block (the `/app/<key>` check in §2.4) and that the background process is running;
- Option B: DNS for `ws.` and that the certificate covers it;
- that `VITE_REVERB_*` were set before the last build;
- the Reverb daemon's logs in Forge.
