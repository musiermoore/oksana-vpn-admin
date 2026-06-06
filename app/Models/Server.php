<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;

class Server extends Model
{
    use HasFactory;

    public const PANEL_API_V2_9 = 'v2.9.*';
    public const PANEL_API_V3_2_8 = 'v3.2.8';

    protected $fillable = [
        'name',
        'code',
        'ip',
        'is_https',
        'link_host',
        'panel_link',
        'panel_username',
        'panel_password',
        'panel_api_version',
        'app_path',
        'ssh_private_key',
        'ssh_public_key',
        'is_vless',
        'is_ready',
        'allowed_inbound_ids',
    ];

    protected function casts(): array
    {
        return [
            'panel_password' => 'encrypted',
            'is_https' => 'boolean',
            'is_vless' => 'boolean',
            'is_ready' => 'boolean',
            'allowed_inbound_ids' => 'array',
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    public function shadowsocksConfigs(): HasMany
    {
        return $this->hasMany(ShadowsocksConfig::class);
    }

    public function getSlugCodeAttribute(): string
    {
        return str($this->code)->slug()->lower();
    }

    public function getSshCommandAttribute(): string
    {
        $sshKeyPath = $this->getSshPrivateKeyPath();

        return "ssh -i {$sshKeyPath} -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=15 root@{$this->ip} 2>&1";
    }

    public function getSshPrivateKeyPath(): string
    {
        $privateKey = trim((string) $this->ssh_private_key);

        if ($privateKey === '') {
            return '/var/www/html/storage/ssh_key';
        }

        $directory = storage_path('app/ssh');
        $path = $directory . '/server-' . $this->id . '-key.pem';

        File::ensureDirectoryExists($directory);

        if (! File::exists($path) || trim((string) File::get($path)) !== $privateKey) {
            File::put($path, $privateKey . PHP_EOL);
        }

        @chmod($path, 0600);

        return $path;
    }

    public function getScheme(): string
    {
        return $this->is_https ? 'https' : 'http';
    }

    public function getHost()
    {
        return $this->link_host ?: $this->ip;
    }

    public function getLinkAddressHost(): string
    {
        $host = trim((string) ($this->link_host ?: $this->ip));

        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $parsedHost = parse_url($host, PHP_URL_HOST);

            return is_string($parsedHost) && $parsedHost !== '' ? $parsedHost : $host;
        }

        if (preg_match('/^\[[^\]]+\](?::\d+)?$/', $host) === 1) {
            return (string) preg_replace('/:\d+$/', '', $host);
        }

        if (substr_count($host, ':') === 1 && preg_match('/:\d+$/', $host) === 1) {
            return (string) preg_replace('/:\d+$/', '', $host);
        }

        return $host;
    }

    /**
     * @return array<int, int>
     */
    public function getAllowedInboundIds(): array
    {
        return collect($this->allowed_inbound_ids ?? [])
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    public function getPanelApiVersion(): string
    {
        return (string) ($this->panel_api_version ?: self::PANEL_API_V2_9);
    }
}
