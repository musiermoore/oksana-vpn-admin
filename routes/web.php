<?php

use App\Http\Controllers\ApiRequestLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CurrentPaymentController;
use App\Http\Controllers\ExtraPaymentController;
use App\Http\Controllers\LimitController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TelegramApp\AuthController as TelegramAppAuthController;
use App\Http\Controllers\TelegramApp\ConnectionController as TelegramAppConnectionController;
use App\Http\Controllers\TelegramApp\PageController as TelegramAppPageController;
use App\Http\Controllers\TelegramApp\PaymentController as TelegramAppPaymentController;
use App\Http\Controllers\TelegramApp\ReferralController as TelegramAppReferralController;
use App\Http\Controllers\TelegramApp\SupportTicketController as TelegramAppSupportTicketController;
use App\Http\Controllers\TelegramApp\UserController as TelegramAppUserController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\UserTokenController;
use App\Http\Controllers\VlessConfigController;
use App\Http\Controllers\WelcomeMessageController;
use App\Http\Controllers\WireGuardController;
use App\Http\Controllers\XrayConfigController;
use App\Http\Controllers\XuiDebugController;
use App\Http\Middleware\BasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware([BasicAuth::class, 'guest'])->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login/code', [AuthController::class, 'sendCode'])->name('login.code');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [WireGuardController::class, 'activePeers'])->name('wireguard.active-peers');
    Route::get('traffic', [WireGuardController::class, 'traffic'])->name('wireguard.traffic');
    Route::get('api-request-logs', [ApiRequestLogController::class, 'index'])->name('api-request-logs.index');
    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('referrals/{user}', [ReferralController::class, 'show'])->name('referrals.show');

    Route::resource('users', UserController::class);
    Route::resource('subscriptions', UserSubscriptionController::class)->only(['index', 'edit', 'update']);
    Route::get('notifications/create', [NotificationController::class, 'create'])
        ->name('notifications.create');
    Route::post('notifications', [NotificationController::class, 'store'])
        ->name('notifications.store');
    Route::get('messages/welcome', [WelcomeMessageController::class, 'edit'])
        ->name('messages.welcome.edit');
    Route::put('messages/welcome', [WelcomeMessageController::class, 'update'])
        ->name('messages.welcome.update');
    Route::resource('user-tokens', UserTokenController::class)->except(['edit', 'update']);
    Route::resource('configs', ConfigController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class);
    Route::resource('current-payments', CurrentPaymentController::class);
    Route::resource('servers', ServerController::class);
    Route::resource('limits', LimitController::class)->except(['edit', 'update', 'show']);
    Route::resource('extra-payments', ExtraPaymentController::class)->except(['edit', 'update', 'show']);
    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('support-tickets/{ticketId}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{ticketId}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply');

    Route::get('configs/create-bulk', [ConfigController::class, 'createBulk'])
        ->name('configs.create-bulk');
    Route::post('configs/bulk', [ConfigController::class, 'storeBulk'])
        ->name('configs.store-bulk');
    Route::post('configs/{config}/enable', [ConfigController::class, 'enable'])
        ->name('configs.enable');
    Route::post('configs/{config}/disable', [ConfigController::class, 'disable'])
        ->name('configs.disable');
    Route::get('xray-configs', [XrayConfigController::class, 'index'])->name('xray-configs.index');
    Route::get('xray-configs/create', [XrayConfigController::class, 'create'])->name('xray-configs.create');
    Route::post('xray-configs', [XrayConfigController::class, 'store'])->name('xray-configs.store');
    Route::get('xray-configs/{protocol}/{config}/edit', [XrayConfigController::class, 'edit'])->name('xray-configs.edit');
    Route::put('xray-configs/{protocol}/{config}', [XrayConfigController::class, 'update'])->name('xray-configs.update');
    Route::delete('xray-configs/{protocol}/{config}', [XrayConfigController::class, 'destroy'])->name('xray-configs.destroy');
    Route::post('xray-configs/{protocol}/{config}/enable', [XrayConfigController::class, 'enable'])->name('xray-configs.enable');
    Route::post('xray-configs/{protocol}/{config}/disable', [XrayConfigController::class, 'disable'])->name('xray-configs.disable');

    Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])
        ->name('transactions.approve');
    Route::delete('transactions/{transaction}/decline', [TransactionController::class, 'decline'])
        ->name('transactions.decline');
    Route::get('xui-debug', [XuiDebugController::class, 'index'])->name('xui-debug.index');
    Route::post('xui-debug', [XuiDebugController::class, 'execute'])->name('xui-debug.execute');

    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
});

Route::get('configs/{userToken:token}', [UserController::class, 'configs'])
    ->name('users.configs');
Route::get('configs/{userToken:token}/{config}/download', [ConfigController::class, 'download'])
    ->name('users.configs.download');
Route::get('configs/{userToken:token}/{config}/qr-code', [ConfigController::class, 'qrCode'])
    ->name('users.configs.qr-code');

Route::get('connect', [VlessConfigController::class, 'connect'])
    ->name('vless.connect');
Route::get('connect/deep-link/{client}', [VlessConfigController::class, 'deepLink'])
    ->name('vless.deep-link');

Route::prefix('telegram-app')->name('telegram-app.')->group(function () {
    Route::get('/', [TelegramAppPageController::class, 'home'])->name('home');
    Route::get('wireguard', [TelegramAppPageController::class, 'wireGuard'])->name('pages.wireguard');
    Route::get('vless', [TelegramAppPageController::class, 'vless'])->name('pages.vless');
    Route::get('payments', [TelegramAppPageController::class, 'payments'])->name('pages.payments');
    Route::get('help', [TelegramAppPageController::class, 'help'])->name('pages.help');
    Route::get('chats', [TelegramAppPageController::class, 'chats'])->name('pages.chats');
    Route::get('support', [TelegramAppPageController::class, 'support'])->name('pages.support');
    Route::get('support/{ticketId}', [TelegramAppPageController::class, 'supportShow'])
        ->whereNumber('ticketId')
        ->name('pages.support.show');
    Route::post('auth/telegram', [TelegramAppAuthController::class, 'authenticate'])
        ->name('auth.telegram');

    Route::middleware('telegram.app')->group(function () {
        Route::get('me', [TelegramAppUserController::class, 'show'])->name('me');
        Route::post('logout', [TelegramAppAuthController::class, 'logout'])->name('logout');
        Route::get('subscription-packages', [TelegramAppUserController::class, 'subscriptionPackages'])
            ->name('subscription-packages');
        Route::post('referrals/claim', [TelegramAppReferralController::class, 'claim'])
            ->name('referrals.claim');
        Route::post('payments/subscriptions', [TelegramAppPaymentController::class, 'purchaseSubscription'])
            ->name('payments.subscriptions');
        Route::post('payments/subscription-codes/activate', [TelegramAppPaymentController::class, 'activateSubscriptionCode'])
            ->name('payments.subscription-codes.activate');
        Route::get('wireguard/configs', [TelegramAppConnectionController::class, 'wireGuardConfigs'])
            ->name('wireguard.configs.index');
        Route::get('wireguard/configs/{configId}/download', [TelegramAppConnectionController::class, 'wireGuardDownload'])
            ->whereNumber('configId')
            ->name('wireguard.configs.download');
        Route::get('wireguard/configs/{configId}/qr-code', [TelegramAppConnectionController::class, 'wireGuardQrCode'])
            ->whereNumber('configId')
            ->name('wireguard.configs.qr-code');
        Route::post('wireguard/configs/{configId}/send-file', [TelegramAppConnectionController::class, 'wireGuardSendFile'])
            ->whereNumber('configId')
            ->name('wireguard.configs.send-file');
        Route::post('wireguard/configs/{configId}/send-qr', [TelegramAppConnectionController::class, 'wireGuardSendQr'])
            ->whereNumber('configId')
            ->name('wireguard.configs.send-qr');
        Route::get('vless/link', [TelegramAppConnectionController::class, 'vlessLinks'])
            ->name('vless.link');
        Route::get('vless/qr-code', [TelegramAppConnectionController::class, 'vlessQrCode'])
            ->name('vless.qr-code');
        Route::post('vless/send-qr', [TelegramAppConnectionController::class, 'vlessSendQr'])
            ->name('vless.send-qr');

        Route::get('support/tickets', [TelegramAppSupportTicketController::class, 'index'])
            ->name('support.tickets.index');
        Route::post('support/tickets', [TelegramAppSupportTicketController::class, 'store'])
            ->name('support.tickets.store');
        Route::get('support/tickets/{ticketId}', [TelegramAppSupportTicketController::class, 'show'])
            ->name('support.tickets.show');
        Route::post('support/tickets/{ticketId}/messages', [TelegramAppSupportTicketController::class, 'addMessage'])
            ->name('support.tickets.messages.store');
    });
});
