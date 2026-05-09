<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('price');
            $table->timestamp('renewal_success_notified_at')->nullable()->after('renewal_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'renewal_reminder_sent_at',
                'renewal_success_notified_at',
            ]);
        });
    }
};
