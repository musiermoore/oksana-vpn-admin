<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('users', UserController::class);
Route::resource('configs', ConfigController::class);
Route::resource('transactions', TransactionController::class);
