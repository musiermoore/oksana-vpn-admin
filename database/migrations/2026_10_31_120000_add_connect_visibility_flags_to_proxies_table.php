<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->boolean('is_visible_in_connect')->default(true)->after('hide_main_node_name');
            $table->boolean('is_visible_in_connect_v2')->default(true)->after('is_visible_in_connect');
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropColumn(['is_visible_in_connect', 'is_visible_in_connect_v2']);
        });
    }
};
