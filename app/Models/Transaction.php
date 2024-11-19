<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'is_approved'
    ];

    public function user()
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::make($this->attributes['created_at'])->format('d.m.Y H:i');
    }
}
