<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_json_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->json('dns')->nullable();
            $table->json('routing')->nullable();
            $table->json('geodata')->nullable();
            $table->json('raw')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'id']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_json_settings');
    }
};
