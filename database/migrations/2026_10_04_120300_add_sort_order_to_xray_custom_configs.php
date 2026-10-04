<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_custom_configs', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['is_active', 'sort_order', 'id'], 'xray_cc_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('xray_custom_configs', function (Blueprint $table): void {
            $table->dropIndex('xray_cc_active_order_idx');
            $table->dropColumn('sort_order');
        });
    }
};
