<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_custom_config_user_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_custom_config_user_id')
                ->constrained('xray_custom_config_user')
                ->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamp('effective_from');
            $table->timestamps();
            $table->index(['xray_custom_config_user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_user_prices');
    }
};
