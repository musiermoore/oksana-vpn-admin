<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referrer_id')
                ->nullable()
                ->after('telegram_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedInteger('referral_accumulated_discount_percent')
                ->default(0)
                ->after('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
            $table->dropColumn('referral_accumulated_discount_percent');
        });
    }
};
