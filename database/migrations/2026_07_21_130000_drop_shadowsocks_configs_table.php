<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shadowsocks_configs');
    }

    public function down(): void
    {
        Schema::create('shadowsocks_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('inbound_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('enable')->default(true);
            $table->unsignedInteger('port')->nullable();
            $table->string('method')->nullable();
            $table->string('password')->nullable();
            $table->string('plugin')->nullable();
            $table->text('plugin_opts')->nullable();
            $table->string('network')->nullable();
            $table->string('security')->nullable();
            $table->string('host')->nullable();
            $table->string('path')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'inbound_id']);
        });
    }
};
