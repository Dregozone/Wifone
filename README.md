# Wifone

A web-based, audio-only voice calling proof-of-concept built with Laravel, Reverb, and WebRTC. Authenticated users can call each other in real-time over Wi-Fi directly in the browser.

## Requirements

- PHP 8.4+
- Composer
- Node.js & npm
- A database (SQLite, MySQL, etc.)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repo-url> wifone
   cd wifone
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JS dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure the database** in `.env`, then run migrations:
   ```bash
   php artisan migrate
   ```

6. **Build frontend assets**
   ```bash
   npm run build
   ```

## Running the App

```bash
composer run dev
```

This starts the web server, Reverb WebSocket server, queue worker, and Vite dev server together. Alternatively, use [Laravel Herd](https://herd.laravel.com/) for the web server and run `composer run dev` for the remaining processes.

## How to Use

1. **Register** two user accounts at `/register`.
2. **Log in** with each account in separate browser windows/tabs.
3. On the main page, you'll see a list of **online users**.
4. Click **Call** next to a user to initiate a voice call.
5. The other user will see an **incoming call modal** — they can Accept or Reject.
6. On accept, the WebRTC audio connection is established automatically.
7. Either user can click **Hang Up** to end the call.

> **Note:** Your browser will request microphone access when a call is initiated — you must allow this for audio to work.
