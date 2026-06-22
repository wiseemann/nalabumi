<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\RegencyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing');
})->name('landing');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
Route::get('/map', [MapController::class, 'index'])->name('map');
Route::get('/archive', [ArchiveController::class, 'index'])->name('archive');
Route::get('/chat', [ChatController::class, 'index'])->name('chat');
Route::get('/regency', [RegencyController::class, 'index'])->name('regency');
