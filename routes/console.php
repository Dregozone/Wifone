<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional: with Forge's scheduler enabled, abandoned calls are tidied every minute.
// Without it they are still expired whenever someone starts a call or opens their call history.
Schedule::call(fn () => Call::expireStale())->everyMinute()->name('calls:expire-stale')->withoutOverlapping();
