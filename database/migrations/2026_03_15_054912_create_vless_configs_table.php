<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vless_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->uuid('uuid');

            $table->unsignedInteger('port');

            $table->string('type')->default('tcp');
            $table->string('encryption')->default('none');
            $table->string('security')->default('reality');

            $table->string('pbk')->nullable();
            $table->string('fp')->nullable();
            $table->string('sni')->nullable();
            $table->string('sid')->nullable();
            $table->string('spx')->nullable();

            $table->timestamps();

            $table->index(['server_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vless_configs');
    }
};
