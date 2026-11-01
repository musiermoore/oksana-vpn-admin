<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyGroupConfigurationRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'proxy_group_id',
        'started_by',
        'group_name',
        'status',
        'revision',
        'steps',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'steps' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function proxyGroup(): BelongsTo
    {
        return $this->belongsTo(ProxyGroup::class);
    }

    /** @return array<string, mixed> */
    public function toStatusArray(): array
    {
        return [
            'id' => (int) $this->id,
            'group_name' => (string) $this->group_name,
            'status' => (string) $this->status,
            'revision' => (int) $this->revision,
            'steps' => $this->steps ?? [],
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
        ];
    }
}
