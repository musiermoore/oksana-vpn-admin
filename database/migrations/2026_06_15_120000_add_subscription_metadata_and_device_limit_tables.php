<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_devices')->default(0)->after('is_active');
            $table->unsignedBigInteger('traffic_limit_bytes')->default(0)->after('max_devices');
            $table->timestamp('subscription_expires_at')->nullable()->after('traffic_limit_bytes');
        });

        Schema::create('active_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('config_type', 32);
            $table->unsignedBigInteger('config_id');
            $table->string('protocol', 32)->nullable();
            $table->string('ip', 45);
            $table->timestamp('first_seen');
            $table->timestamp('last_seen');
            $table->timestamps();

            $table->unique(['server_id', 'config_type', 'config_id', 'ip'], 'active_connections_unique_connection');
            $table->index(['user_id', 'last_seen'], 'active_connections_user_last_seen_idx');
            $table->index(['user_id', 'ip', 'last_seen'], 'active_connections_user_ip_last_seen_idx');
            $table->index(['config_type', 'config_id', 'last_seen'], 'active_connections_config_last_seen_idx');
        });

        Schema::create('blocked_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('config_type', 32);
            $table->unsignedBigInteger('config_id');
            $table->string('reason');
            $table->timestamp('blocked_until');
            $table->timestamps();

            $table->unique(['config_type', 'config_id'], 'blocked_configs_unique_config');
            $table->index(['user_id', 'blocked_until'], 'blocked_configs_user_until_idx');
            $table->index(['blocked_until'], 'blocked_configs_until_idx');
        });

        Schema::create('user_server_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'server_id'], 'user_server_stats_user_server_unique');
            $table->index(['user_id', 'updated_at'], 'user_server_stats_user_updated_idx');
        });

        Schema::create('user_server_stat_histories', function (Blueprint $table) {
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

        DB::table('users')
            ->select(['users.id'])
            ->orderBy('users.id')
            ->lazy()
            ->each(function (object $user): void {
                $expiresAt = DB::table('user_subscriptions')
                    ->where('user_id', $user->id)
                    ->max('end_date');

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'subscription_expires_at' => $expiresAt ? $expiresAt.' 23:59:59' : null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_server_stat_histories');
        Schema::dropIfExists('user_server_stats');
        Schema::dropIfExists('blocked_configs');
        Schema::dropIfExists('active_connections');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'max_devices',
                'traffic_limit_bytes',
                'subscription_expires_at',
            ]);
        });
    }
};
