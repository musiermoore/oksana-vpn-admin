<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('max_devices')
            ->orWhere('max_devices', 0)
            ->update([
                'max_devices' => 10,
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_devices')->default(10)->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->where('max_devices', 10)
            ->update([
                'max_devices' => 0,
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_devices')->default(0)->change();
        });
    }
};
