<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPeriod extends Model
{
    use HasFactory;

    protected $table = 'current_payments';

    protected $fillable = [
        'start_date',
        'end_date',
        'amount',
    ];

    public const HOSTING_PRICE = 660;

    public function getFormattedStartDateAttribute()
    {
        return Carbon::parse($this->attributes['start_date'])->format('d.m.Y');
    }

    public function getFormattedEndDateAttribute()
    {
        return Carbon::parse($this->attributes['end_date'])->format('d.m.Y');
    }

    public function getFullDateAttribute()
    {
        return $this->formatted_start_date . ' - ' . $this->formatted_end_date;
    }

    public static function getHostingPrice(): int
    {
        return 50;
    }

    public static function getActivePaymentPeriodId(): ?int
    {
        return self::query()
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('start_date')
            ->value('id');
    }

    public static function getPreviousPaymentPeriodId(): ?int
    {
        $activePeriodStartDate = self::query()
            ->whereId(self::getActivePaymentPeriodId())
            ->value('start_date');

        if (! $activePeriodStartDate) {
            return null;
        }

        return self::query()
            ->where('start_date', '<', $activePeriodStartDate)
            ->orderByDesc('start_date')
            ->value('id');
    }

    public static function getActive(): ?self
    {
        return self::query()
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('start_date')
            ->first();
    }

    public static function getNextAfterDate(string $date): ?self
    {
        return self::query()
            ->whereDate('start_date', '>', $date)
            ->orderBy('start_date')
            ->first();
    }
}
