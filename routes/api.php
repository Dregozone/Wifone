<?php

use App\Http\Controllers\Api\DailyStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/stats/daily', DailyStatsController::class)->middleware('throttle:30,1');
