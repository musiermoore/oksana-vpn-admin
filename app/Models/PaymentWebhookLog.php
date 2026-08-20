<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentWebhookLog extends Model
{
    use HasFactory;

    public const SOURCE_EXTERNAL = 'external';

    public const SOURCE_REPLAY = 'replay';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'source',
        'user_id',
        'invoice_id',
        'transaction_id',
        'replayed_from_log_id',
        'event',
        'provider_payment_id',
        'request_method',
        'request_url',
        'request_ip',
        'request_user_agent',
        'request_headers',
        'request_payload',
        'status',
        'response_status',
        'response_payload',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function replayedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replayed_from_log_id');
    }

    public function replays(): HasMany
    {
        return $this->hasMany(self::class, 'replayed_from_log_id');
    }
}
