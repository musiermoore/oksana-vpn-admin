<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->string('protocol')->default('vless')->after('port');
        });

        DB::table('vless_configs')
            ->whereNull('protocol')
            ->update(['protocol' => 'vless']);
    }

    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropColumn('protocol');
        });
    }
};
