<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/urgency-scores', [MapController::class, 'urgencyScores']);
Route::get('/regions/{id}/details', [MapController::class, 'regionDetails']);
Route::post('/chat', [ChatController::class, 'ask']);
Route::get('/articles', [\App\Http\Controllers\Api\ArticleController::class, 'index']);
