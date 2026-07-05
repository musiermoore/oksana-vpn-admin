<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_payment_id',
        'status',
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
        'paid',
        'amount',
        'currency',
        'description',
        'confirmation_url',
        'payload',
        'history',
        'paid_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'bool',
            'amount' => 'float',
            'tax_estimated_commission' => 'float',
            'payload' => 'array',
            'history' => 'array',
            'tax_request_payload' => 'array',
            'tax_response_payload' => 'array',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
            'tax_queued_at' => 'datetime',
            'tax_sent_at' => 'datetime',
            'tax_last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
