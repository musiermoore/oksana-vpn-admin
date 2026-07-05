<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('tax_status')->default('not_sent')->after('status');
            $table->timestamp('tax_queued_at')->nullable()->after('tax_status');
            $table->timestamp('tax_sent_at')->nullable()->after('tax_queued_at');
            $table->timestamp('tax_last_error_at')->nullable()->after('tax_sent_at');
            $table->string('tax_receipt_uuid')->nullable()->after('tax_last_error_at');
            $table->string('tax_service_name')->nullable()->after('tax_receipt_uuid');
            $table->decimal('tax_estimated_commission', 10, 2)->nullable()->after('tax_service_name');
            $table->text('tax_error_message')->nullable()->after('tax_estimated_commission');
            $table->json('tax_request_payload')->nullable()->after('tax_error_message');
            $table->json('tax_response_payload')->nullable()->after('tax_request_payload');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'tax_status',
                'tax_queued_at',
                'tax_sent_at',
                'tax_last_error_at',
                'tax_receipt_uuid',
                'tax_service_name',
                'tax_estimated_commission',
                'tax_error_message',
                'tax_request_payload',
                'tax_response_payload',
            ]);
        });
    }
};
