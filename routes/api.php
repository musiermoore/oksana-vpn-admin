<?php

use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\BasicAuth;
use App\Http\Middleware\TrackApiRequests;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware([BasicAuth::class, TrackApiRequests::class])->group(function () {
    Route::get('users/{telegram}/{type}/configs', [UserController::class, 'getUserConfigs'])
        ->name('users.configs');
    Route::get('users/{telegram}/configs/{type}/{config}/download', [UserController::class, 'downloadConfig'])
        ->name('users.configs.download');
    Route::get('users/{telegram}/configs/{type}/{config}/qr-code', [UserController::class, 'downloadQrCode'])
        ->name('users.configs.qr-code');
    Route::get('users/{telegram}/vless/link', [UserController::class, 'getVlessLink'])
        ->name('users.configs.vless.link');
    Route::get('users/{telegram}/vless/qr-code', [UserController::class, 'getVlessQrCode'])
        ->name('users.configs.vless.qr-code');
    Route::get('users/{telegram}/balance', [UserController::class, 'balance'])
        ->name('users.balance');
    Route::post('users/{telegram}/save-id', [UserController::class, 'saveTelegramId'])
        ->name('users.save-telegram-id');

    Route::post('users/{telegram}/transactions', [TransactionController::class, 'store'])
        ->name('users.transactions.store');

    Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])
        ->name('transactions.approve');
    Route::delete('transactions/{transaction}/decline', [TransactionController::class, 'decline'])
        ->name('transactions.decline');
});
