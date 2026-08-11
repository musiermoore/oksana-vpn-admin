<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiveawayPrize extends Model
{
    use HasFactory;

    public const ALLOWED_DURATION_MONTHS = [1, 3, 6, 12];

    protected $fillable = [
        'giveaway_id',
        'duration_months',
        'quantity',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function resolvedTitle(): string
    {
        if (! empty($this->title)) {
            return $this->title;
        }

        return sprintf('Подписка на %d %s', $this->duration_months, $this->duration_months === 1 ? 'месяц' : 'месяцев');
    }
}
