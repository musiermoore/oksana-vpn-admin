<?php

use Database\Seeders\TransactionTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $types = (new TransactionTypeSeeder())->items();

        foreach ($types as $type) {
            DB::table('transaction_types')->insert([
                'name' => $type['name'],
                'slug' => $type['slug'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
            $table->dropColumn('description');
        });

        Schema::dropIfExists('transaction_types');
    }
};
