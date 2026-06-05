<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShadowsocksConfig extends Model
{
    protected $fillable = [
        'server_id',
        'inbound_id',
        'user_id',
        'name',
        'description',
        'is_active',
        'enable',
        'port',
        'method',
        'password',
        'plugin',
        'plugin_opts',
        'network',
        'security',
        'host',
        'path',
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
        $credentials = $this->encodeCredentials();
        $query = $this->buildQueryString();
        $label = rawurlencode((string) str($this->server->code.'_'.$this->name)->slug());

        return "ss://{$credentials}@{$this->server->getLinkAddressHost()}:{$this->port}{$query}#{$label}";
    }

    public function getQrCodeContent(): string
    {
        return $this->getLink();
    }

    private function encodeCredentials(): string
    {
        $value = "{$this->method}:{$this->password}";

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function buildQueryString(): string
    {
        $plugin = $this->buildPluginValue();

        if ($plugin === null) {
            return '';
        }

        return '?plugin='.rawurlencode($plugin);
    }

    private function buildPluginValue(): ?string
    {
        if (! $this->plugin) {
            return null;
        }

        $parts = [$this->plugin];

        if ($this->plugin_opts) {
            $parts[] = $this->plugin_opts;
        }

        return implode(';', $parts);
    }
}
