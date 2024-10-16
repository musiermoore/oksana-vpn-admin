<?php

use App\Services\WireGuardTrafficService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('high_traffic_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\App\Models\Config::class)->constrained()->cascadeOnDelete();
            $table->enum('type', WireGuardTrafficService::ALLOWED_TYPES);
            $table->double('amount');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('high_traffic_logs');
    }
};
