<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#16a34a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Wifone">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@auth
    @php
        $iceServers = [['urls' => 'stun:stun.l.google.com:19302']];

        if (config('services.turn.urls')) {
            $iceServers[] = [
                'urls' => config('services.turn.urls'),
                'username' => config('services.turn.username'),
                'credential' => config('services.turn.credential'),
            ];
        }
    @endphp

    <script>
        window.authUserId = {{ auth()->id() }};
        window.iceServers = @js($iceServers);
    </script>
@endauth
