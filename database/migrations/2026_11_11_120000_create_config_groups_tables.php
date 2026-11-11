<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('config_group_items', function (Blueprint $table): void {
            $table->foreignId('config_group_id')->constrained()->cascadeOnDelete();
            $table->morphs('groupable');
            $table->unique(['config_group_id', 'groupable_type', 'groupable_id'], 'config_group_items_unique');
            $table->unique(['groupable_type', 'groupable_id'], 'config_group_items_single_group');
            $table->index(['groupable_type', 'groupable_id'], 'config_group_items_morph_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_group_items');
        Schema::dropIfExists('config_groups');
    }
};
