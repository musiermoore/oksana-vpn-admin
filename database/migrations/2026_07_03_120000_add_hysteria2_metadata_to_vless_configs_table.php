<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->string('alpn')->nullable()->after('pbk');
            $table->string('obfs')->nullable()->after('mode');
            $table->string('obfs_password')->nullable()->after('obfs');
        });
    }

    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropColumn(['alpn', 'obfs', 'obfs_password']);
        });
    }
};
