<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->boolean('hide_main_node_name')
                ->default(false)
                ->after('xray_inbound_id');
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropColumn('hide_main_node_name');
        });
    }
};
