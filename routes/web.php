<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CurrentPaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTokenController;
use App\Http\Controllers\WireGuardController;
use App\Http\Middleware\BasicAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', [WireGuardController::class, 'activePeers']);

Route::middleware(BasicAuth::class)->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('user-tokens', UserTokenController::class)->except(['edit', 'update']);
    Route::resource('configs', ConfigController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class);
    Route::resource('current-payments', CurrentPaymentController::class);

    Route::get('configs-wg/create', [ConfigController::class, 'createWg'])
        ->name('configs-wg.create');
    Route::post('configs-wg', [ConfigController::class, 'storeWg'])
        ->name('configs-wg.store');
});

Route::get('configs/{userToken:token}', [UserController::class, 'configs'])
    ->withoutMiddleware(BasicAuth::class)
    ->name('users.configs');
Route::get('configs/{userToken:token}/{config}/download', [ConfigController::class, 'download'])
    ->name('users.configs.download');
Route::get('configs/{userToken:token}/{config}/qr-code', [ConfigController::class, 'qrCode'])
    ->name('users.configs.qr-code');
