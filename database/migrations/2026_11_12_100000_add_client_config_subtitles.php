<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table): void {
            $table->string('subtitle', 500)->nullable();
        });

        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->string('subtitle', 500)->nullable();
        });

        Schema::table('xray_custom_configs', function (Blueprint $table): void {
            $table->string('subtitle', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('xray_custom_configs', fn (Blueprint $table) => $table->dropColumn('subtitle'));
        Schema::table('vless_external_subscriptions', fn (Blueprint $table) => $table->dropColumn('subtitle'));
        Schema::table('vless_configs', fn (Blueprint $table) => $table->dropColumn('subtitle'));
    }
};
