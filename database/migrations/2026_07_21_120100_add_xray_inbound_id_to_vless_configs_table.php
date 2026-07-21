<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->foreignId('xray_inbound_id')
                ->nullable()
                ->after('inbound_id')
                ->constrained('xray_inbounds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vless_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('xray_inbound_id');
        });
    }
};
