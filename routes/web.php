<?php

use App\Http\Controllers\CallController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::post('calls', [CallController::class, 'store'])->name('calls.store');
    Route::post('calls/{call}/accept', [CallController::class, 'accept'])->name('calls.accept');
    Route::post('calls/{call}/reject', [CallController::class, 'reject'])->name('calls.reject');
    Route::post('calls/{call}/end', [CallController::class, 'end'])->name('calls.end');
});

require __DIR__.'/settings.php';
