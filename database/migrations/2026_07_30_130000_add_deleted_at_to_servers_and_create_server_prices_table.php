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
            $table->softDeletes()->after('updated_at');
        });

        Schema::create('server_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['server_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_prices');

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
