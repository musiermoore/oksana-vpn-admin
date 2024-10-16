<?php

namespace App\Models;

use App\Services\WireGuardConfigService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Config extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'user_id',
        'name',
        'description',
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

        $units = [
            'bytes',
            'KB',
            'MB',
            'GB',
            'TB'
        ];

        $sentUnit = 0;
        while ($sent > 1024 && $formatted) {
            $sent /= 1024;
            $sentUnit++;
        }

        $receivedUnit = 0;
        while ($received > 1024 && $formatted) {
            $received /= 1024;
            $receivedUnit++;
        }

        if (empty($sent) && empty($received)) {
            return [];
        }

        return [
            'sent' => round($sent, 2) . ($formatted ? ' ' . $units[$sentUnit] : ''),
            'received' => round($received, 2) . ($formatted ? ' ' . $units[$receivedUnit] : '')
        ];
    }

    public function createWgConfig(): bool
    {
        return WireGuardConfigService::instance($this->server, $this->name)->create();
    }

    public function deleteWgConfig(): bool
    {
        return WireGuardConfigService::instance($this->server, $this->name)->delete();
    }
}
