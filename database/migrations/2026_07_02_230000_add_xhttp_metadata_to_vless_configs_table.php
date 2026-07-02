<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->string('mode')->nullable()->after('service_name');
            $table->text('extra')->nullable()->after('mode');
            $table->string('x_padding_bytes')->nullable()->after('extra');
        });
    }

    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropColumn(['mode', 'extra', 'x_padding_bytes']);
        });
    }
};
