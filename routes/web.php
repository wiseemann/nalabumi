<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegencyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/', function () {
    return Inertia::render('Landing');
})->name('landing');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
Route::get('/archive', [ArchiveController::class, 'index'])->name('archive');
Route::get('/archive/export', [ArchiveController::class, 'export'])->name('archive.export');
Route::get('/chat', [ChatController::class, 'index'])->name('chat');
Route::get('/regency', [RegencyController::class, 'index'])->name('regency');

// Admin Authentication
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Admin Panel (protected)
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/articles', [AdminController::class, 'store'])->name('admin.articles.store');
    Route::patch('/articles/{id}/verify', [AdminController::class, 'verify'])->name('admin.articles.verify');
    Route::patch('/articles/{id}/reject', [AdminController::class, 'reject'])->name('admin.articles.reject');
    Route::post('/rss', [AdminController::class, 'storeRss'])->name('admin.rss.store');
    Route::post('/embedding', [AdminController::class, 'generateEmbeddings'])->name('admin.embedding');
    Route::post('/deduplicate', [AdminController::class, 'deleteDuplicates'])->name('admin.deduplicate');
});
