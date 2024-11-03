<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CurrentPaymentController;
use App\Http\Controllers\LimitController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTokenController;
use App\Http\Controllers\WireGuardController;
use App\Http\Middleware\BasicAuth;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::get('/', [WireGuardController::class, 'activePeers'])->name('wireguard.active-peers');

Route::middleware(BasicAuth::class)->group(function () {
    Route::get('traffic', [WireGuardController::class, 'traffic'])->name('wireguard.traffic');

    Route::resource('users', UserController::class);
    Route::resource('user-tokens', UserTokenController::class)->except(['edit', 'update']);
    Route::resource('configs', ConfigController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class);
    Route::resource('current-payments', CurrentPaymentController::class);
    Route::resource('servers', ServerController::class);
    Route::resource('limits', LimitController::class)->except(['edit', 'update', 'show']);

    Route::get('configs/create-bulk', [ConfigController::class, 'createBulk'])
        ->name('configs.create-bulk');
    Route::post('configs/bulk', [ConfigController::class, 'storeBulk'])
        ->name('configs.store-bulk');
});

Route::get('configs/{userToken:token}', [UserController::class, 'configs'])
    ->withoutMiddleware(BasicAuth::class)
    ->name('users.configs');
Route::get('configs/{userToken:token}/{config}/download', [ConfigController::class, 'download'])
    ->name('users.configs.download');
Route::get('configs/{userToken:token}/{config}/qr-code', [ConfigController::class, 'qrCode'])
    ->name('users.configs.qr-code');


// Inertia
Route::middleware(HandleInertiaRequests::class)->prefix('vue')->name('vue.')->group(function () {
    Route::middleware(BasicAuth::class)->group(function () {
        Route::resource('users', \App\Http\Controllers\Inertia\UserController::class);
    });
});
