<?php

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
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->string('flow')->nullable()->after('security');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropColumn('flow');
        });
    }
};
