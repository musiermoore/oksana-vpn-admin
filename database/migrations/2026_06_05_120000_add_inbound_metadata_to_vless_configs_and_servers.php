<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->json('auto_pull_vless_types')->nullable()->after('is_ready');
        });

        Schema::table('vless_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('inbound_id')->nullable()->after('server_id');
            $table->string('host')->nullable()->after('sni');
            $table->string('path')->nullable()->after('host');
            $table->string('service_name')->nullable()->after('path');

            $table->index(['server_id', 'inbound_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'inbound_id']);
            $table->dropColumn(['inbound_id', 'host', 'path', 'service_name']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('auto_pull_vless_types');
        });
    }
};
