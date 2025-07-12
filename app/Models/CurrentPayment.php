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
}
