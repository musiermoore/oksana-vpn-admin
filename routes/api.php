<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\BasicAuth;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware(BasicAuth::class)->group(function () {
    Route::get('user/{telegram}/configs', [UserController::class, 'getUserConfigs'])
        ->name('users.configs');
    Route::get('user/{telegram}/configs/{config}/download', [UserController::class, 'downloadConfig'])
        ->name('users.configs.download');
    Route::get('user/{telegram}/configs/{config}/qr-code', [UserController::class, 'downloadQrCode'])
        ->name('users.configs.qr-code');
});
