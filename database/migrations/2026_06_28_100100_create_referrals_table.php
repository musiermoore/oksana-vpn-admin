<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referral_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('qualifying_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedInteger('invitee_bonus_days')->default(0);
            $table->unsignedInteger('referrer_reward_percent')->default(0);
            $table->string('reward_status')->default('pending');
            $table->dateTime('reward_scheduled_at')->nullable();
            $table->dateTime('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique('referral_user_id');
            $table->unique(['referrer_id', 'referral_user_id']);
            $table->index(['referrer_id', 'reward_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
