<?php

use App\Models\Invoice;
use App\Models\PaymentWebhookLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('source')->default(PaymentWebhookLog::SOURCE_EXTERNAL);
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Invoice::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Transaction::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('replayed_from_log_id')->nullable()->constrained('payment_webhook_logs')->nullOnDelete();
            $table->string('event')->nullable();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('request_method', 10)->default('POST');
            $table->string('request_url')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->string('status')->default(PaymentWebhookLog::STATUS_RECEIVED);
            $table->integer('response_status')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
