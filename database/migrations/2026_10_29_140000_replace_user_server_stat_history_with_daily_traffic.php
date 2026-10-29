<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_traffic', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('traffic_date');
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'traffic_date'], 'user_daily_traffic_user_date_unique');
            $table->index('traffic_date', 'user_daily_traffic_date_idx');
        });

        Schema::dropIfExists('user_server_stat_histories');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_traffic');

        Schema::create('user_server_stat_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['user_id', 'collected_at'], 'user_server_stat_histories_user_collected_idx');
            $table->index(['server_id', 'collected_at'], 'user_server_stat_histories_server_collected_idx');
        });
    }
};
