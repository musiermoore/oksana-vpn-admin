<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('port')->default(443);
            $table->boolean('is_https')->default(true);
            $table->boolean('is_ready')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('proxy_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proxy_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_server');
        Schema::dropIfExists('proxies');
    }
};
