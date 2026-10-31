<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->string('system_name')->default('')->after('name');
        });

        DB::table('proxies')->update(['system_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropColumn('system_name');
        });
    }
};
