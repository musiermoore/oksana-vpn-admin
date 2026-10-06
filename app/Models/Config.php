<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\WireGuardConfigServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Config extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'user_id',
        'name',
        'description',
        'is_active',
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

    public function traffic(): HasMany
    {
        return $this->hasMany(Traffic::class);
    }

    public function highTrafficLogs(): HasMany
    {
        return $this->hasMany(HighTrafficLog::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(Limit::class);
    }

    public function getPathAttribute()
    {
        return storage_path("app/wireguard/clients-{$this->server->slug_code}/$this->name.conf");
    }

    public function getLastTrafficAttribute()
    {
        return once(fn () => $this->getLastTraffic(false));
    }

    public function getFormattedLastTrafficAttribute()
    {
        return once(fn () => $this->getLastTraffic(true));
    }

    public function getSentTrafficAttribute()
    {
        return once(fn () => $this->getLastTraffic()['sent'] ?? 0);
    }

    public function getReceivedTrafficAttribute()
    {
        return once(fn () => $this->getLastTraffic()['received'] ?? 0);
    }

    public function getAddressAttribute()
    {
        try {
            $content = file_get_contents($this->path);

            if (preg_match('/^Address\s*=\s*(.+)$/m', $content, $matches)) {
                // Return the address without any leading/trailing whitespace
                return trim($matches[1]);
            }

            return 'Файл не найден.';
        } catch (\Exception $exception) {
            report($exception);

            return 'Ошибка при загрузке файла.';
        }
    }

    public function getLastTraffic(bool $formatted = false): array
    {
        if ($this->traffic->count() <= 1) {
            return [];
        }

        $startIntervalTraffic = $this->traffic->first();
        $endIntervalTraffic = $this->traffic->last();

        $sent = $endIntervalTraffic->sent - $startIntervalTraffic->sent;
        $received = $endIntervalTraffic->received - $startIntervalTraffic->received;

        if (empty($sent) && empty($received)) {
            return [];
        }

        return [
            'sent' => $this->formatTrafficValue($sent, $formatted),
            'received' => $this->formatTrafficValue($received, $formatted),
        ];
    }

    private function formatTrafficValue(int|float $value, bool $formatted): string
    {
        $units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;

        while ($value > 1024 && $formatted) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 2).($formatted ? ' '.$units[$unit] : '');
    }

    public function createWgConfig(): bool
    {
        return WireGuardConfigServiceFactory::make($this)->create();
    }

    public function createWgConfigOrFail(): bool
    {
        return WireGuardConfigServiceFactory::make($this)->createOrFail();
    }

    public function deleteWgConfig(): bool
    {
        return WireGuardConfigServiceFactory::make($this)->delete();
    }

    public function enableWgConfig(): bool
    {
        return WireGuardConfigServiceFactory::make($this)->enable();
    }

    public function disableWgConfig(): bool
    {
        return WireGuardConfigServiceFactory::make($this)->disable();
    }

    public function setSpeedLimit(int|string $limit): bool
    {
        return WireGuardConfigServiceFactory::make($this)->setLimit($limit);
    }

    public function removeSpeedLimit(int|string $limit): bool
    {
        return WireGuardConfigServiceFactory::make($this)->removeLimit($limit);
    }

    public function getQrCodeContent(): string
    {
        return (string) file_get_contents($this->path);
    }

    public function getDownloadFilenameAttribute(): string
    {
        $serverName = (string) ($this->server?->name ?? '');
        $serverCode = (string) ($this->server?->code ?? '');

        $normalized = Str::of($serverName !== '' ? $serverName : $serverCode)
            ->slug()
            ->replace('-', '')
            ->replaceMatches('/\d+/', '')
            ->value();

        if ($normalized === '') {
            $normalized = Str::of($this->name)
                ->slug()
                ->replace('-', '')
                ->replaceMatches('/\d+/', '')
                ->value();
        }

        return $normalized !== '' ? $normalized.'.conf' : 'wireguard.conf';
    }
}
