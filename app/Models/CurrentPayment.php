<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'amount',
    ];

    public const HOSTING_PRICE = 660;

    public static function getHostingPrice(): int
    {
        return ceil(self::HOSTING_PRICE / User::count());
    }

    public static function getHostingPriceForAllMonths(): ?int
    {
        return self::query()
            ->where('start_date', '<=', Carbon::now())
            ->sum('amount');
    }
}
