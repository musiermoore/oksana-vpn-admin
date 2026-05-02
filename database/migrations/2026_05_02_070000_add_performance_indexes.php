<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic', function (Blueprint $table) {
            $table->index(['config_id', 'created_at'], 'traffic_config_created_at_idx');
        });

        Schema::table('high_traffic_logs', function (Blueprint $table) {
            $table->index(['config_id', 'created_at'], 'high_traffic_logs_config_created_at_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['is_approved', 'created_at'], 'transactions_approved_created_at_idx');
            $table->index(['user_id', 'type_id', 'is_approved', 'created_at'], 'transactions_user_type_approved_created_at_idx');
        });

        Schema::table('current_payments', function (Blueprint $table) {
            $table->index(['start_date', 'end_date'], 'current_payments_start_end_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('telegram', 'users_telegram_idx');
            $table->index('telegram_id', 'users_telegram_id_idx');
            $table->index(['is_active', 'deleted_at'], 'users_is_active_deleted_at_idx');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'end_date'], 'user_subscriptions_user_end_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('traffic', function (Blueprint $table) {
            $table->dropIndex('traffic_config_created_at_idx');
        });

        Schema::table('high_traffic_logs', function (Blueprint $table) {
            $table->dropIndex('high_traffic_logs_config_created_at_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_approved_created_at_idx');
            $table->dropIndex('transactions_user_type_approved_created_at_idx');
        });

        Schema::table('current_payments', function (Blueprint $table) {
            $table->dropIndex('current_payments_start_end_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_telegram_idx');
            $table->dropIndex('users_telegram_id_idx');
            $table->dropIndex('users_is_active_deleted_at_idx');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('user_subscriptions_user_end_date_idx');
        });
    }
};
