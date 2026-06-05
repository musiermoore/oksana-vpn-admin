<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('allowed_vless_inbounds', 'allowed_inbound_ids');
            $table->dropColumn('auto_pull_vless_types');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->json('auto_pull_vless_types')->nullable()->after('is_ready');
            $table->renameColumn('allowed_inbound_ids', 'allowed_vless_inbounds');
        });
    }
};
