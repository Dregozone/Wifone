<?php

namespace App\Http\Controllers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BroadcastAuthController extends BroadcastController
{
    /**
     * Authenticate the request for channel access.
     *
     * DEV BYPASS: If the session has no authenticated user but a user_id
     * parameter is present, log in as that user so private/presence
     * channels work while the session-cookie issue is being resolved.
     */
    public function authenticate(Request $request)
    {
        if (! $request->user() && $request->input('user_id')) {
            Auth::loginUsingId((int) $request->input('user_id'));
        }

        return parent::authenticate($request);
    }
}
