<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VlessConfig extends Model
{
    protected $fillable = [
        'server_id',
        'user_id',
        'name', // email in 3x-ui
        'description',
        'is_active',

        'uuid',

        'port',

        'type',
        'encryption',
        'security',
        'flow',

        'pbk',
        'fp',
        'sni',
        'sid',
        'spx',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function getLinkAttribute(): string
    {
        return $this->getLink();
    }

    public function getLink(): string
    {
        $paramList = [
            "type={$this->type}",
            "encryption={$this->encryption}",
            "security={$this->security}",
            "pbk={$this->pbk}",
            "fp={$this->fp}",
            "sni={$this->sni}",
            "sid={$this->sid}",
            "spx=" . urlencode($this->spx),
        ];

        if ($this->flow) {
            $paramList[] = "flow={$this->flow}";
        }

        $params = implode('&', $paramList);

        $label = str($this->server->code . '_' . $this->name)->slug();

        return "vless://{$this->uuid}@{$this->server->ip}:{$this->port}?{$params}#{$label}";
    }

    public function getQrCodeContent(): string
    {
        return $this->getLink();
    }
}
