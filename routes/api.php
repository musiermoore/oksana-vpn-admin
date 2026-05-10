<?php

use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\BasicAuth;
use App\Http\Middleware\TrackApiRequests;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware([BasicAuth::class, TrackApiRequests::class])->group(function () {
    Route::post('users/register', [UserController::class, 'register'])
        ->name('users.register');
    Route::get('users/{telegramId}/{type}/configs', [UserController::class, 'getUserConfigs'])
        ->name('users.configs');
    Route::get('users/{telegramId}/configs/{type}/{config}/download', [UserController::class, 'downloadConfig'])
        ->name('users.configs.download');
    Route::get('users/{telegramId}/configs/{type}/{config}/qr-code', [UserController::class, 'downloadQrCode'])
        ->name('users.configs.qr-code');
    Route::get('users/{telegramId}/vless/link', [UserController::class, 'getVlessLink'])
        ->name('users.configs.vless.link');
    Route::get('users/{telegramId}/vless/qr-code', [UserController::class, 'getVlessQrCode'])
        ->name('users.configs.vless.qr-code');
    Route::get('users/{telegramId}/balance', [UserController::class, 'balance'])
        ->name('users.balance');
    Route::post('users/{telegramId}/save-id', [UserController::class, 'saveTelegramId'])
        ->name('users.save-telegram-id');

    Route::post('users/{telegramId}/transactions', [TransactionController::class, 'store'])
        ->name('users.transactions.store');

    Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])
        ->name('transactions.approve');
    Route::delete('transactions/{transaction}/decline', [TransactionController::class, 'decline'])
        ->name('transactions.decline');
});
