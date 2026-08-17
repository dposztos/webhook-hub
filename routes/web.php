<?php

use App\Http\Controllers\Api\EndpointController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\TreeController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::prefix('api')->group(function () {
        Route::get('/tree', [GroupController::class, 'tree']);
        Route::post('/tree/move', [TreeController::class, 'move']);

        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{group}', [GroupController::class, 'update']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

        Route::post('/endpoints', [EndpointController::class, 'store']);
        Route::get('/endpoints/{endpoint}', [EndpointController::class, 'show']);
        Route::put('/endpoints/{endpoint}', [EndpointController::class, 'update']);
        Route::delete('/endpoints/{endpoint}', [EndpointController::class, 'destroy']);
        Route::post('/endpoints/{endpoint}/rotate-secret', [EndpointController::class, 'rotateSecret']);
        Route::delete('/endpoints/{endpoint}/messages', [MessageController::class, 'clear']);

        Route::get('/messages', [MessageController::class, 'index']);
        Route::get('/messages/{uuid}', [MessageController::class, 'show']);
        Route::get('/messages/{uuid}/raw', [MessageController::class, 'raw']);
        Route::get('/messages/{uuid}/variables', [TestController::class, 'variables']);
        Route::post('/messages/{uuid}/replay', [MessageController::class, 'replay']);
        Route::post('/messages/{uuid}/unread', [MessageController::class, 'markUnread']);
        Route::post('/messages/read-all', [MessageController::class, 'markAllRead']);
        Route::delete('/messages/{uuid}', [MessageController::class, 'destroy']);

        Route::get('/rules', [RuleController::class, 'index']);
        Route::post('/rules', [RuleController::class, 'store']);
        Route::get('/rules/{rule}', [RuleController::class, 'show']);
        Route::put('/rules/{rule}', [RuleController::class, 'update']);
        Route::delete('/rules/{rule}', [RuleController::class, 'destroy']);

        Route::post('/test/conditions', [TestController::class, 'conditions']);
        Route::post('/test/action', [TestController::class, 'action']);
    });

    // Az egyoldalas felület minden más útvonalon (a /u/ ingest kivételével).
    Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!u/|api/).*$');
});
