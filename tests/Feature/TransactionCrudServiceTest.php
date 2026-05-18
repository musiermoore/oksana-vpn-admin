<?php

namespace Tests\Feature;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use App\Services\Crud\TransactionCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Telegram\Bot\Laravel\Facades\Telegram;
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

        app(TransactionCrudService::class)->create(new \App\DTOs\Transaction\TransactionData(
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

        app(TransactionCrudService::class)->create(new \App\DTOs\Transaction\TransactionData(
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
        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['chat_id'] === '654321'
                    && $payload['text'] === 'Баланс был пополнен на 100';
            })
            ->andReturnTrue();
        Telegram::swap($telegram);

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 100,
        ]);

        Server::query()->create([
            'name' => 'Ready WG',
            'code' => 'RWG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => false,
        ]);

        $user = $this->createUser(balance: 0, telegramId: '654321');

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 100,
            'is_approved' => false,
            'description' => 'Pending deposit',
        ]);

        app(TransactionCrudService::class)->approve($transaction);

        $this->assertSame(0.0, $user->fresh()->balance);

        $subscription = UserSubscription::query()
            ->where('user_id', $user->id)
            ->sole();

        $this->assertSame('2026-05-18', $subscription->start_date);
        $this->assertSame('2026-06-18', $subscription->end_date);
        $this->assertSame(100.0, (float) $subscription->price);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_approved' => true,
            'current_balance_amount' => 100,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -100,
            'is_approved' => true,
            'description' => 'Продление подписки',
            'current_balance_amount' => 0,
        ]);

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($user) {
            return $job->userId === $user->id;
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
