<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'xray_custom_config_user_prices';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('xray_custom_config_user_id');
                $table->decimal('price', 10, 2);
                $table->timestamp('effective_from');
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex($tableName, ['xray_custom_config_user_id', 'effective_from'])) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(
                    ['xray_custom_config_user_id', 'effective_from'],
                    'xray_ccup_assignment_eff_idx',
                );
            });
        }

        $hasAssignmentForeignKey = collect(Schema::getForeignKeys($tableName))
            ->contains(static fn (array $foreignKey): bool =>
                in_array('xray_custom_config_user_id', $foreignKey['columns'], true)
                && $foreignKey['foreign_table'] === 'xray_custom_config_user'
            );

        if (! $hasAssignmentForeignKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('xray_custom_config_user_id', 'xray_ccup_assignment_fk')
                    ->references('id')
                    ->on('xray_custom_config_user')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_user_prices');
    }
};
