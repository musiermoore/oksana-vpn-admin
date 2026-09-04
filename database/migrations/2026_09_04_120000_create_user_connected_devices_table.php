<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_connected_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('device')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('first_connection_at')->nullable();
            $table->timestamp('last_connection_at');
            $table->unsignedInteger('connection_count')->default(0);
            $table->string('connection_route', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at'], 'user_connected_devices_user_deleted_idx');
            $table->index(
                ['user_id', 'user_agent_hash', 'connection_route', 'deleted_at'],
                'user_connected_devices_user_agent_route_idx'
            );
            $table->index(['user_id', 'last_connection_at'], 'user_connected_devices_user_last_connection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_connected_devices');
    }
};
