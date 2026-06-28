<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->string('source')->default('purchase')->after('price');
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('source')
                ->constrained('transactions')
                ->nullOnDelete();
            $table->json('meta')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn(['source', 'meta']);
        });
    }
};
