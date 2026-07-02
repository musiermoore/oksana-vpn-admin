<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;

class Server extends Model
{
    use HasFactory;

    public const PANEL_API_V2_9 = 'v2.9.*';
    public const PANEL_API_V3_2_8 = 'v3.2.8';
    public const TYPE_WIREGUARD_OLD = 'wireguard-old';
    public const TYPE_WIREGUARD = 'wireguard';
    public const TYPE_VLESS = 'vless';

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
        'type',
        'is_vless',
        'is_ready',
        'hide_configs_for_non_admins',
        'allowed_inbound_ids',
    ];

    protected function casts(): array
    {
        return [
            'panel_password' => 'encrypted',
            'is_https' => 'boolean',
            'is_vless' => 'boolean',
            'is_ready' => 'boolean',
            'hide_configs_for_non_admins' => 'boolean',
            'allowed_inbound_ids' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_WIREGUARD_OLD,
            self::TYPE_WIREGUARD,
            self::TYPE_VLESS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function wireGuardTypes(): array
    {
        return [
            self::TYPE_WIREGUARD_OLD,
            self::TYPE_WIREGUARD,
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

    public function proxies(): BelongsToMany
    {
        return $this->belongsToMany(Proxy::class)
            ->withTimestamps();
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

    public function getTypeAttribute(?string $value): string
    {
        if (is_string($value) && in_array($value, self::allowedTypes(), true)) {
            return $value;
        }

        return $this->resolveLegacyType((bool) ($this->attributes['is_vless'] ?? false));
    }

    public function setTypeAttribute(?string $value): void
    {
        $type = $this->normalizeType($value);

        $this->attributes['type'] = $type;
        $this->attributes['is_vless'] = $type === self::TYPE_VLESS;
    }

    public function getIsVlessAttribute(?bool $value): bool
    {
        if (array_key_exists('type', $this->attributes) && $this->attributes['type'] !== null) {
            return $this->type === self::TYPE_VLESS;
        }

        return (bool) $value;
    }

    public function setIsVlessAttribute(bool $value): void
    {
        $isVless = (bool) $value;

        $this->attributes['is_vless'] = $isVless;
        $this->attributes['type'] = $this->resolveLegacyType($isVless);
    }

    public function isVlessType(): bool
    {
        return $this->type === self::TYPE_VLESS;
    }

    public function isWireGuardType(): bool
    {
        return in_array($this->type, self::wireGuardTypes(), true);
    }

    public function isLegacyWireGuardType(): bool
    {
        return $this->type === self::TYPE_WIREGUARD_OLD;
    }

    public function isModernWireGuardType(): bool
    {
        return $this->type === self::TYPE_WIREGUARD;
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $this->normalizeType($type));
    }

    /**
     * @param  array<int, string>  $types
     */
    public function scopeWhereTypeIn(Builder $query, array $types): Builder
    {
        return $query->whereIn('type', array_map(
            fn (string $type) => $this->normalizeType($type),
            $types,
        ));
    }

    public function scopeWireGuard(Builder $query): Builder
    {
        return $query->whereIn('type', self::wireGuardTypes());
    }

    public function scopeLegacyWireGuard(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WIREGUARD_OLD);
    }

    public function scopeModernWireGuard(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WIREGUARD);
    }

    public function scopeVless(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_VLESS);
    }

    private function normalizeType(?string $type): string
    {
        $normalized = trim(mb_strtolower((string) $type));

        return in_array($normalized, self::allowedTypes(), true)
            ? $normalized
            : self::TYPE_WIREGUARD_OLD;
    }

    private function resolveLegacyType(bool $isVless): string
    {
        return $isVless ? self::TYPE_VLESS : self::TYPE_WIREGUARD_OLD;
    }
}
