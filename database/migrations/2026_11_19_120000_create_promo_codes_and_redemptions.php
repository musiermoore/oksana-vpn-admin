<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('benefit', 32);
            $table->decimal('value', 10, 2);
            $table->boolean('once_per_user')->default(true);
            $table->boolean('new_users_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->foreignId('subscription_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('code', 64);
            $table->string('benefit', 32);
            $table->decimal('value', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->boolean('new_users_only');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'promo_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
