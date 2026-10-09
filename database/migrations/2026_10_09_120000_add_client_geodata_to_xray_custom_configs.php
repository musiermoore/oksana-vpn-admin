<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_custom_config_geodata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_custom_config_id')->constrained('xray_custom_configs')->cascadeOnDelete();
            $table->string('client_key', 32);
            $table->foreignId('geodata_id')->constrained('xray_routing_geodata')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['xray_custom_config_id', 'client_key']);
            $table->index(['client_key', 'geodata_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_geodata');
    }
};
