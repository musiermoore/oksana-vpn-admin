<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->unsignedInteger('months')->nullable();
            $table->unsignedInteger('days')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['buyer_user_id', 'status']);
            $table->index(['activated_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_codes');
    }
};
