<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giveaways', function (Blueprint $table) {
            $table->boolean('admins_only')->default(false)->after('description');
            $table->index(['admins_only', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('giveaways', function (Blueprint $table) {
            $table->dropIndex(['admins_only', 'status']);
            $table->dropColumn('admins_only');
        });
    }
};
