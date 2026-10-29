<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'user_server_stat_histories_user_server_collected_idx';

    public function up(): void
    {
        Schema::table('user_server_stat_histories', function (Blueprint $table): void {
            $table->index(['user_id', 'server_id', 'collected_at'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('user_server_stat_histories', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
