<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'preset',
        'action',
        'method',
        'endpoint',
        'status',
        'response_status',
        'error_message',
        'request_payload',
        'response_headers',
        'response_body',
        'response_json',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_headers' => 'array',
            'response_json' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
}
