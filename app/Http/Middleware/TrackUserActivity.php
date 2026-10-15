<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $now = now();

            User::query()
                ->whereKey($user->getKey())
                ->where(function (Builder $query) use ($now): void {
                    $query->whereNull('last_active_at')
                        ->orWhere('last_active_at', '<=', $now->copy()->subHour());
                })
                ->update(['last_active_at' => $now]);
        }

        return $next($request);
    }
}
