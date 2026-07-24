<?php

namespace Tests\Feature;

use App\DTOs\Transaction\TransactionData;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\SendTelegramMessageJob;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Crud\TransactionCrudService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_creating_an_approved_deposit_updates_balance_once(): void
    {
        $user = $this->createUser(balance: 0);

        app(TransactionCrudService::class)->create(new TransactionData(
            userId: $user->id,
            typeId: TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            amount: 100,
            description: 'Manual deposit',
            isApproved: true,
        ));

        $this->assertSame(100.0, $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 100,
            'current_balance_amount' => 100,
            'is_approved' => true,
        ]);
    }

    public function test_creating_a_subscription_from_positive_input_saves_negative_amount_and_updates_balance_once(): void
    {
        $user = $this->createUser(balance: 200);

        app(TransactionCrudService::class)->create(new TransactionData(
            userId: $user->id,
            typeId: TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            amount: 100,
            description: 'Manual subscription charge',
            isApproved: true,
        ));

        $transaction = Transaction::query()->sole();

        $this->assertSame(-100.0, (float) $transaction->amount);
        $this->assertSame(100.0, $user->fresh()->balance);
        $this->assertSame(-100.0, (float) $transaction->current_balance_amount);
    }

    public function test_negative_transactions_use_charge_wording(): void
    {
        $user = $this->createUser(balance: 50, telegramId: '123456');
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_EXTRA_PAYMENT),
            'amount' => -25,
            'is_approved' => false,
            'description' => 'Extra payment',
        ]);

        $this->assertSame('С баланса было списано 25', $transaction->approval_message_text);
    }

    public function test_approving_a_deposit_creates_subscription_and_dispatches_default_configs_for_user(): void
    {
        Queue::fake();

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 150,
        ]);

        Server::query()->create([
            'name' => 'Ready WG',
            'code' => 'RWG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD_OLD,
        ]);

        $user = $this->createUser(balance: 200, telegramId: '654321');

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'Pending deposit',
            'extra_data' => [
                'subscription_months' => 6,
                'base_month_price' => 150.0,
                'discount_percent' => 20,
                'package_full_price' => 900.0,
                'package_price' => 720.0,
                'balance_before' => 200.0,
                'deposit_amount' => 520.0,
            ],
        ]);

        app(TransactionCrudService::class)->approve($transaction);

        $subscription = UserSubscription::query()
            ->where('user_id', $user->id)
            ->sole();

        $this->assertSame('2026-05-18', $subscription->start_date);
        $this->assertSame('2026-11-18', $subscription->end_date);
        $this->assertSame(720.0, (float) $subscription->price);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_approved' => true,
            'current_balance_amount' => 520,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -720,
            'is_approved' => true,
            'description' => 'Покупка подписки на 6 мес.',
            'current_balance_amount' => -200,
        ]);

        $this->assertSame(0.0, $user->fresh()->balance);

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($user) {
            return $job->userId === $user->id;
        });

        Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
            return $job->payload['chat_id'] === '654321'
                && $job->payload['text'] === 'Подписка успешно активирована до 18.11.2026.';
        });
    }

    public function test_approving_regular_deposit_keeps_balance_top_up_message(): void
    {
        Queue::fake();

        $user = $this->createUser(balance: 0, telegramId: '777000');

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 100,
            'is_approved' => false,
            'description' => 'Regular deposit',
        ]);

        app(TransactionCrudService::class)->approve($transaction);

        $this->assertSame(100.0, $user->fresh()->balance);

        Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
            return $job->payload['chat_id'] === '777000'
                && $job->payload['text'] === 'Баланс был пополнен на 100';
        });
    }

    public function test_approving_package_deposit_creates_next_subscription_record_when_current_subscription_is_active(): void
    {
        Queue::fake();

        CurrentPayment::query()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'amount' => 150,
        ]);

        $user = $this->createUser(balance: 0, telegramId: '654322');

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-06-12',
            'price' => 150,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 150,
            'is_approved' => false,
            'description' => 'Pending deposit',
            'extra_data' => [
                'subscription_months' => 1,
                'base_month_price' => 150.0,
                'discount_percent' => 0,
                'package_full_price' => 150.0,
                'package_price' => 150.0,
                'balance_before' => 0.0,
                'deposit_amount' => 150.0,
            ],
        ]);

        app(TransactionCrudService::class)->approve($transaction);

        $this->assertSame(2, UserSubscription::query()->count());

        $subscription->refresh();
        $this->assertSame('2026-05-12', $subscription->start_date);
        $this->assertSame('2026-06-12', $subscription->end_date);
        $this->assertSame(150.0, (float) $subscription->price);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-06-13',
            'end_date' => '2026-07-13',
            'price' => 150,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -150,
            'is_approved' => true,
            'description' => 'Покупка подписки на 1 мес.',
        ]);

        Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
            return $job->payload['chat_id'] === '654322'
                && $job->payload['text'] === 'Подписка успешно продлена до 13.07.2026.';
        });
    }

    private function createUser(float $balance, string $telegramId = '100200'): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => $telegramId,
            'join_at' => '2026-05-01',
            'balance' => $balance,
            'password' => bcrypt('password'),
        ]);
    }
}
