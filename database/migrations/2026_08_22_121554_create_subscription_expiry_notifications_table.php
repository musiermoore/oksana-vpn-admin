<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_expiry_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('threshold_key', 16);
            $table->unsignedSmallInteger('threshold_hours');
            $table->timestamp('subscription_end_at');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_subscription_id', 'threshold_key'], 'subscription_expiry_notifications_unique');
            $table->index('user_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_expiry_notifications');
    }
};
