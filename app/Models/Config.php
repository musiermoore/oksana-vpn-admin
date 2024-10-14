<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Config extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function traffic(): HasMany
    {
        return $this->hasMany(Traffic::class);
    }

    public function getPathAttribute()
    {
        return storage_path('app/configs/' . $this->name . '.conf');
    }

    public function getLastTrafficAttribute()
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
        while ($sent > 1024) {
            $sent /= 1024;
            $sentUnit++;
        }

        $receivedUnit = 0;
        while ($received > 1024) {
            $received /= 1024;
            $receivedUnit++;
        }

        return [
            'sent' => round($sent, 2) . ' ' . $units[$sentUnit],
            'received' => round($received, 2) . ' ' . $units[$receivedUnit]
        ];
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
}
