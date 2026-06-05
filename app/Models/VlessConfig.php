<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VlessConfig extends Model
{
    protected $fillable = [
        'server_id',
        'inbound_id',
        'user_id',
        'name', // email in 3x-ui
        'description',
        'is_active',
        'enable',

        'uuid',
        'sub_id',

        'port',

        'type',
        'encryption',
        'security',
        'flow',

        'pbk',
        'fp',
        'sni',
        'host',
        'path',
        'service_name',
        'sid',
        'spx',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enable' => 'boolean',
        ];
    }

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
        if (empty($this->sub_id)) {
            return $this->getStaticLink();
        }

        return $this->getSubscriptionLink();
    }

    public function getStaticLink(): string
    {
        $paramList = [
            "type={$this->type}",
            "encryption={$this->encryption}",
            "security={$this->security}",
        ];

        if ($this->security === 'reality') {
            $paramList[] = "pbk={$this->pbk}";
            $paramList[] = "fp={$this->fp}";
            $paramList[] = "sni={$this->sni}";
            $paramList[] = "sid={$this->sid}";
            $paramList[] = 'spx=' . urlencode($this->spx ?: '/');
        } else {
            if ($this->security && $this->sni) {
                $paramList[] = "sni={$this->sni}";
            }

            if ($this->type === 'ws') {
                if ($this->host) {
                    $paramList[] = 'host=' . urlencode($this->host);
                }

                if ($this->path) {
                    $paramList[] = 'path=' . urlencode($this->path);
                }
            }

            if ($this->type === 'grpc' && $this->service_name) {
                $paramList[] = 'serviceName=' . urlencode($this->service_name);
            }
        }

        if ($this->flow) {
            $paramList[] = "flow={$this->flow}";
        }

        $params = implode('&', $paramList);

        $label = str($this->server->code . '_' . $this->name)->slug();

        return "vless://{$this->uuid}@{$this->server->getHost()}:{$this->port}?{$params}#{$label}";
    }

    public function getBaseUrl(): string
    {
        return "{$this->server->getScheme()}://{$this->server->getHost()}";
    }

    public function getSubscriptionLink(): string
    {
        return "{$this->getBaseUrl()}/sub/{$this->sub_id}";
    }

    public function getQrCodeContent(): string
    {
        return $this->getLink();
    }
}
