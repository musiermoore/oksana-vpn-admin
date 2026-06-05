<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shadowsocks_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inbound_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('enable')->default(true);

            $table->unsignedInteger('port');
            $table->string('method');
            $table->string('password');
            $table->string('plugin')->nullable();
            $table->text('plugin_opts')->nullable();
            $table->string('network')->nullable();
            $table->string('security')->nullable();
            $table->string('host')->nullable();
            $table->string('path')->nullable();

            $table->timestamps();

            $table->index(['server_id', 'user_id']);
            $table->index(['server_id', 'inbound_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shadowsocks_configs');
    }
};
