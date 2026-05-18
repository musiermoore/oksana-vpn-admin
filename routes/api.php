<?php

use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\BasicAuth;
use App\Http\Middleware\TrackApiRequests;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware([BasicAuth::class, TrackApiRequests::class])->group(function () {
    Route::post('users/register', [UserController::class, 'register'])
        ->name('users.register');

    Route::prefix('users/{telegramId}')->name('users.')->group(function () {
        Route::get('registration-status', [UserController::class, 'registrationStatus'])
            ->name('registration-status');
        Route::post('save-id', [UserController::class, 'saveTelegramId'])
            ->name('save-telegram-id');

        Route::middleware('api.user')->group(function () {
            Route::get('balance', [UserController::class, 'balance'])
                ->name('balance');
            Route::post('transactions', [TransactionController::class, 'store'])
                ->name('transactions.store');

            Route::middleware('api.user.access')->group(function () {
                Route::get('{type}/configs', [UserController::class, 'getUserConfigs'])
                    ->name('configs');
                Route::get('configs/{type}/{config}/download', [UserController::class, 'downloadConfig'])
                    ->name('configs.download');
                Route::get('configs/{type}/{config}/qr-code', [UserController::class, 'downloadQrCode'])
                    ->name('configs.qr-code');
                Route::get('vless/link', [UserController::class, 'getVlessLink'])
                    ->name('configs.vless.link');
                Route::get('vless/qr-code', [UserController::class, 'getVlessQrCode'])
                    ->name('configs.vless.qr-code');
            });
        });
    });

    Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])
        ->name('transactions.approve');
    Route::delete('transactions/{transaction}/decline', [TransactionController::class, 'decline'])
        ->name('transactions.decline');
});
