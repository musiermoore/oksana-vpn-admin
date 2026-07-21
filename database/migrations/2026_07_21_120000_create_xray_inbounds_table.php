<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_inbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->json('params')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_inbounds');
    }
};
