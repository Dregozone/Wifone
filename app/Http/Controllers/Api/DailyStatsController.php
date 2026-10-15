<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('services.stats.token');

        abort_if(! is_string($token) || $token === '', 404);

        $providedToken = $request->bearerToken();

        abort_unless(is_string($providedToken) && hash_equals($token, $providedToken), 401);

        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $validated['date'] ?? CarbonImmutable::now('Europe/London')->subDay()->toDateString(),
            'Europe/London',
        );
        $start = $date->startOfDay()->utc();
        $end = $date->addDay()->startOfDay()->utc();
        $now = CarbonImmutable::now();

        $logins = DB::table('logins')
            ->where('logged_in_at', '>=', $start)
            ->where('logged_in_at', '<', $end);

        return response()->json([
            'app' => 'Wifone',
            'date' => $date->toDateString(),
            'signups' => User::query()
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->count(),
            'logins' => [
                'total' => (clone $logins)->count(),
                'unique' => (clone $logins)->distinct()->count('user_id'),
            ],
            'users' => [
                'total' => User::query()->count(),
                'active_7d' => User::query()
                    ->whereBetween('last_active_at', [$now->subDays(7), $now])
                    ->count(),
                'active_30d' => User::query()
                    ->whereBetween('last_active_at', [$now->subDays(30), $now])
                    ->count(),
            ],
        ]);
    }
}
