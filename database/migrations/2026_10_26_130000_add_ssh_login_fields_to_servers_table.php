<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('ssh_username')->default('root');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->text('ssh_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn(['ssh_username', 'ssh_port', 'ssh_password']);
        });
    }
};
