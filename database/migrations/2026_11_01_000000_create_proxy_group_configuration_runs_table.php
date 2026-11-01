<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_group_configuration_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proxy_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('group_name');
            $table->string('status')->default('pending');
            $table->unsignedInteger('revision')->default(0);
            $table->json('steps')->default('[]');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['proxy_group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_group_configuration_runs');
    }
};
