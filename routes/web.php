<?php

use App\Http\Controllers\CallController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::prefix('call')->middleware('auth')->group(function () {
    Route::post('initiate', [CallController::class, 'initiateCall'])->name('call.initiate');
    Route::post('accept', [CallController::class, 'acceptCall'])->name('call.accept');
    Route::post('reject', [CallController::class, 'rejectCall'])->name('call.reject');
    Route::post('end', [CallController::class, 'endCall'])->name('call.end');
    Route::post('offer', [CallController::class, 'sendOffer'])->name('call.offer');
    Route::post('answer', [CallController::class, 'sendAnswer'])->name('call.answer');
    Route::post('candidate', [CallController::class, 'sendCandidate'])->name('call.candidate');
});

require __DIR__.'/settings.php';
