<?php

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\Invoice;
use App\Models\PaymentWebhookLog;
use App\Models\Referral;
use App\Models\SubscriptionCode;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Jobs\EditTelegramMessageTextJob;
use App\Jobs\ReconcileUserAccessStateJob;
use App\Jobs\SendTelegramMessageJob;
use Carbon\Carbon;
use App\Services\ReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-12 12:00:00');
        config()->set('services.telegram.dev_chat_id', '999999');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_webhook_approves_pending_transaction_when_payment_succeeds(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'status' => 'pending',
            'paid' => false,
            'amount' => 520,
            'currency' => 'RUB',
            'description' => 'Подписка 6 мес. для @alice',
            'history' => [[
                'type' => 'payment.created',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'occurred_at' => '2026-06-12T09:50:00.000Z',
                'payload' => ['status' => 'pending'],
            ]],
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
            'extra_data' => [
                'subscription_months' => 6,
                'package_price' => 720,
            ],
        ]);

        $this->postJson('/api/payment/webhook', [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '23d93cac-000f-5000-8000-126628f15141',
                'status' => 'succeeded',
                'paid' => true,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'description' => 'Подписка 6 мес. для @alice',
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yookassa.example/confirm',
                ],
                'created_at' => '2026-06-12T10:00:00.000Z',
            ],
        ])->assertOk()
            ->assertExactJson([
                'ok' => true,
            ]);

        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'succeeded',
            'paid' => true,
        ]);

        $invoice->refresh();
        $this->assertCount(2, $invoice->history);
        $this->assertSame('payment.created', $invoice->history[0]['type']);
        $this->assertSame('payment.succeeded', $invoice->history[1]['type']);
        $this->assertSame('succeeded', $invoice->history[1]['status']);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -720,
            'is_approved' => true,
            'description' => 'Покупка подписки на 6 мес.',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-12-12',
            'price' => 720,
        ]);

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, fn (DispatchDefaultConfigsForUserJob $job): bool => $job->userId === $user->id);
        Queue::assertPushed(ReconcileUserAccessStateJob::class, fn (ReconcileUserAccessStateJob $job): bool => $job->userId === $user->id);
        Queue::assertPushed(SendTelegramMessageJob::class, fn (SendTelegramMessageJob $job): bool => $job->payload['chat_id'] === '123456789'
            && $job->payload['text'] === 'Подписка успешно активирована до 12.12.2026.');
        Queue::assertPushed(EditTelegramMessageTextJob::class, fn (EditTelegramMessageTextJob $job): bool => $job->payload['chat_id'] === 777
            && $job->payload['message_id'] === 999
            && $job->payload['text'] === "Оплата получена.\n\nПодписка успешно активирована до 12.12.2026.");

        $approvedDeposit = Transaction::query()
            ->where('invoice_id', $invoice->id)
            ->where('amount', 520)
            ->first();

        $this->assertNotNull($approvedDeposit);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'yookassa',
            'source' => PaymentWebhookLog::SOURCE_EXTERNAL,
            'event' => 'payment.succeeded',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'invoice_id' => $invoice->id,
            'transaction_id' => $approvedDeposit?->id,
            'status' => PaymentWebhookLog::STATUS_PROCESSED,
            'response_status' => 200,
        ]);
    }

    public function test_webhook_payment_schedules_and_applies_referral_reward(): void
    {
        Queue::fake();

        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '111111111',
        ]);

        $user = User::query()->create([
            'name' => 'Invitee',
            'telegram' => '@invitee',
            'telegram_id' => '123456789',
            'balance' => 0,
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $user->id,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15142',
            'status' => 'pending',
            'paid' => false,
            'amount' => 150,
            'currency' => 'RUB',
            'description' => 'Подписка 1 мес. для @invitee',
            'history' => [[
                'type' => 'payment.created',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '150.00',
                    'currency' => 'RUB',
                ],
                'occurred_at' => '2026-06-12T09:50:00.000Z',
                'payload' => ['status' => 'pending'],
            ]],
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 150,
            'is_approved' => false,
            'description' => 'YooKassa',
            'extra_data' => [
                'subscription_months' => 1,
                'package_price' => 150,
            ],
        ]);

        $this->postJson('/api/payment/webhook', [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '23d93cac-000f-5000-8000-126628f15142',
                'status' => 'succeeded',
                'paid' => true,
                'amount' => [
                    'value' => '150.00',
                    'currency' => 'RUB',
                ],
                'description' => 'Подписка 1 мес. для @invitee',
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yookassa.example/confirm',
                ],
                'created_at' => '2026-06-12T10:00:00.000Z',
            ],
        ])->assertOk();

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::WaitingConfirmation, $referral->reward_status);
        $this->assertNotNull($referral->qualifying_transaction_id);
        $this->assertSame(3, $referral->invitee_bonus_days);
        $this->assertSame(5, $referral->referrer_reward_percent);

        Carbon::setTestNow('2026-06-13 12:01:00');

        app(ReferralRewardService::class)->processReward($referral);

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Rewarded, $referral->reward_status);
        $this->assertNotNull($referral->rewarded_at);
        $this->assertSame(5, $referrer->fresh()->referral_accumulated_discount_percent);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'source' => 'referral_bonus',
            'price' => 0,
        ]);
    }

    public function test_webhook_notifies_dev_chat_when_paid_payment_is_canceled(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'status' => 'pending',
            'paid' => true,
            'amount' => 520,
            'currency' => 'RUB',
            'description' => 'Подписка 6 мес. для @alice',
            'history' => [[
                'type' => 'payment.created',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'occurred_at' => '2026-06-12T09:50:00.000Z',
                'payload' => ['status' => 'pending'],
            ]],
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ]);

        $this->postJson('/api/payment/webhook', [
            'type' => 'notification',
            'event' => 'payment.canceled',
            'object' => [
                'id' => '23d93cac-000f-5000-8000-126628f15141',
                'status' => 'canceled',
                'paid' => true,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'description' => 'Подписка 6 мес. для @alice',
                'created_at' => '2026-06-12T10:30:00.000Z',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'canceled',
            'paid' => true,
        ]);

        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'is_approved' => false,
        ]);

        $invoice->refresh();
        $this->assertCount(2, $invoice->history);
        $this->assertSame('payment.canceled', $invoice->history[1]['type']);
        $this->assertSame('canceled', $invoice->history[1]['status']);

        Queue::assertPushed(SendTelegramMessageJob::class, fn (SendTelegramMessageJob $job): bool => $job->payload['chat_id'] === '999999'
            && str_contains((string) $job->payload['text'], 'отменён после оплаты')
            && str_contains((string) $job->payload['text'], '23d93cac-000f-5000-8000-126628f15141'));
        Queue::assertPushed(EditTelegramMessageTextJob::class, fn (EditTelegramMessageTextJob $job): bool => $job->payload['chat_id'] === 777
            && $job->payload['message_id'] === 999
            && $job->payload['text'] === 'Платёж отменён. Ссылка на оплату больше не действует.');
    }

    public function test_webhook_generates_gift_code_when_gift_payment_succeeds(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'status' => 'pending',
            'paid' => false,
            'amount' => 520,
            'currency' => 'RUB',
            'description' => 'Подарочный код 6 мес. от @alice',
            'history' => [[
                'type' => 'payment.created',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'occurred_at' => '2026-06-12T09:50:00.000Z',
                'payload' => ['status' => 'pending'],
            ]],
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
            'extra_data' => [
                'purchase_type' => 'GIFT',
                'subscription_months' => 6,
                'package_price' => 720,
            ],
        ]);

        $this->postJson('/api/payment/webhook', [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '23d93cac-000f-5000-8000-126628f15141',
                'status' => 'succeeded',
                'paid' => true,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'description' => 'Подарочный код 6 мес. от @alice',
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yookassa.example/confirm',
                ],
                'created_at' => '2026-06-12T10:00:00.000Z',
            ],
        ])->assertOk();

        $code = SubscriptionCode::query()->sole();

        $this->assertSame($user->id, $code->buyer_user_id);
        $this->assertSame(6, $code->months);
        $this->assertSame(720.0, $code->price);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -720,
            'is_approved' => true,
            'description' => 'Подарочный код на 6 мес.',
        ]);

        $this->assertDatabaseCount('user_subscriptions', 0);

        Queue::assertPushed(SendTelegramMessageJob::class, fn (SendTelegramMessageJob $job): bool => $job->payload['chat_id'] === '123456789'
            && str_contains((string) $job->payload['text'], 'Подарочный код на 6 мес. готов:')
            && str_contains((string) $job->payload['text'], 'Передайте его получателю для активации в mini-app.'));
        Queue::assertPushed(EditTelegramMessageTextJob::class, fn (EditTelegramMessageTextJob $job): bool => $job->payload['chat_id'] === 777
            && $job->payload['message_id'] === 999
            && str_contains((string) $job->payload['text'], "Оплата получена.\n\nПодарочный код на 6 мес. готов:"));
    }

    public function test_webhook_queues_telegram_side_effects_without_calling_them_synchronously(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'status' => 'pending',
            'paid' => false,
            'amount' => 520,
            'currency' => 'RUB',
            'description' => 'Подписка 6 мес. для @alice',
            'history' => [[
                'type' => 'payment.created',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'occurred_at' => '2026-06-12T09:50:00.000Z',
                'payload' => ['status' => 'pending'],
            ]],
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
            'extra_data' => [
                'subscription_months' => 6,
                'package_price' => 720,
            ],
        ]);

        $this->postJson('/api/payment/webhook', [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '23d93cac-000f-5000-8000-126628f15141',
                'status' => 'succeeded',
                'paid' => true,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'description' => 'Подписка 6 мес. для @alice',
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yookassa.example/confirm',
                ],
                'created_at' => '2026-06-12T10:00:00.000Z',
            ],
        ])->assertOk()
            ->assertExactJson([
                'ok' => true,
            ]);

        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $invoice->id,
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-12-12',
            'price' => 720,
        ]);

        Queue::assertPushed(SendTelegramMessageJob::class);
        Queue::assertPushed(EditTelegramMessageTextJob::class);
    }

    public function test_authorized_user_can_replay_webhook_from_saved_log(): void
    {
        Queue::fake();

        config()->set('auth.basic_auth.login', 'admin');
        config()->set('auth.basic_auth.password', 'secret');

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => '23d93cac-000f-5000-8000-126628f15141',
            'status' => 'pending',
            'paid' => false,
            'amount' => 520,
            'currency' => 'RUB',
            'description' => 'Подписка 6 мес. для @alice',
            'history' => [],
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
            'extra_data' => [
                'subscription_months' => 6,
                'package_price' => 720,
            ],
        ]);

        $sourceLog = PaymentWebhookLog::query()->create([
            'provider' => 'yookassa',
            'source' => PaymentWebhookLog::SOURCE_EXTERNAL,
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'transaction_id' => $transaction->id,
            'event' => 'payment.succeeded',
            'provider_payment_id' => $invoice->provider_payment_id,
            'request_method' => 'POST',
            'request_url' => '/api/payment/webhook',
            'request_payload' => [
                'type' => 'notification',
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => '23d93cac-000f-5000-8000-126628f15141',
                    'status' => 'succeeded',
                    'paid' => true,
                    'amount' => [
                        'value' => '520.00',
                        'currency' => 'RUB',
                    ],
                    'description' => 'Подписка 6 мес. для @alice',
                    'confirmation' => [
                        'type' => 'redirect',
                        'confirmation_url' => 'https://yookassa.example/confirm',
                    ],
                    'created_at' => '2026-06-12T10:00:00.000Z',
                ],
            ],
            'status' => PaymentWebhookLog::STATUS_PROCESSED,
            'response_status' => 200,
            'response_payload' => ['ok' => true],
            'processed_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('admin:secret'),
        ])->postJson("/api/payment/webhook-logs/{$sourceLog->id}/replay")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source_log_id', $sourceLog->id);

        $replayLogId = (int) $response->json('replay_log_id');

        $replayLog = PaymentWebhookLog::query()->find($replayLogId);

        $this->assertNotNull($replayLog);
        $this->assertSame(PaymentWebhookLog::SOURCE_REPLAY, $replayLog?->source);
        $this->assertSame($sourceLog->id, $replayLog?->replayed_from_log_id);
        $this->assertSame(PaymentWebhookLog::STATUS_PROCESSED, $replayLog?->status);
        $this->assertSame($invoice->id, $replayLog?->invoice_id);
        $this->assertSame($transaction->id, $replayLog?->transaction_id);

        $this->assertTrue((bool) $transaction->fresh()?->is_approved);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-12-12',
            'price' => 720,
        ]);
    }
}
