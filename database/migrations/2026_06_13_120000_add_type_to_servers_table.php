<?php

use App\Models\Server;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('type')
                ->default(Server::TYPE_WIREGUARD_OLD)
                ->after('ssh_public_key');
        });

        DB::table('servers')
            ->where('is_vless', true)
            ->update(['type' => Server::TYPE_VLESS]);

        DB::table('servers')
            ->where('is_vless', false)
            ->update(['type' => Server::TYPE_WIREGUARD_OLD]);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
