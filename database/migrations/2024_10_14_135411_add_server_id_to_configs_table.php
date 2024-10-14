<?php

use App\Models\Server;
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
        Schema::table('configs', function (Blueprint $table) {
            $table->foreignIdFor(Server::class)
                ->nullable()
                ->after('id')
                ->constrained();
        });

        DB::table('configs')->update([
            'server_id' => DB::table('servers')->value('id'),
        ]);

        Schema::table('configs', function (Blueprint $table) {
            $table->foreignIdFor(Server::class)
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Server::class);
        });
    }
};
