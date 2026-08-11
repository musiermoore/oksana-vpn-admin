<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Giveaway extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAWING = 'drawing';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'series_id',
        'parent_giveaway_id',
        'sequence_number',
        'title',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(GiveawaySeries::class, 'series_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_giveaway_id');
    }

    public function child(): HasOne
    {
        return $this->hasOne(self::class, 'parent_giveaway_id');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(GiveawayPrize::class)->orderBy('sort_order')->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GiveawayParticipant::class);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(GiveawayWinner::class)->orderBy('prize_slot');
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SCHEDULED,
            self::STATUS_ACTIVE,
            self::STATUS_DRAWING,
            self::STATUS_FINISHED,
            self::STATUS_CANCELLED,
        ];
    }

    public function canEditPrizes(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function canParticipate(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at?->lte(now())
            && $this->ends_at?->isFuture();
    }

    public function durationDays(): int
    {
        return max(1, (int) ceil($this->duration_minutes / 1440));
    }

    public function formattedStatus(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_SCHEDULED => 'Запланирован',
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_DRAWING => 'Определяем победителей',
            self::STATUS_FINISHED => 'Завершён',
            self::STATUS_CANCELLED => 'Отменён',
            default => $this->status,
        };
    }
}
