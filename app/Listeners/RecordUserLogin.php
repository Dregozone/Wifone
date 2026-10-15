<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            DB::table('logins')->insert([
                'user_id' => $event->user->getAuthIdentifier(),
                'logged_in_at' => now(),
            ]);
        }
    }
}
