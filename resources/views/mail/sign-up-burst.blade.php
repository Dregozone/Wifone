<x-mail::message>
# {{ $recentSignUps }} sign-ups in the last {{ $windowMinutes }} minutes

{{ config('app.name') }} is getting sign-ups faster than usual, so they are not being emailed one by one. This is the only email about them for the next {{ $windowMinutes }} minutes.

There are now {{ $totalUsers }} users.
</x-mail::message>
