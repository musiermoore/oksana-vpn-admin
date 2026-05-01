<?php

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
        $depositTypeId = DB::table('transaction_types')
            ->where('slug', 'deposit')
            ->value('id');

        Schema::table('transactions', function (Blueprint $table) use ($depositTypeId) {
            $table->foreignId('type_id')
                ->default($depositTypeId)
                ->after('user_id')
                ->constrained('transaction_types');
            $table->text('description')->nullable()->after('is_approved');
        });

        sleep(1);

        Schema::table('transactions', function (Blueprint $table) use ($depositTypeId) {
            $table->foreignId('type_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
