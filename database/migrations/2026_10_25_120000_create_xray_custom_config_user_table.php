<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_custom_config_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_custom_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['xray_custom_config_id', 'user_id'], 'xray_custom_config_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_user');
    }
};
