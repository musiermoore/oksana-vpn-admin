<?php

namespace App\Models;

use App\Models\Concerns\SyncsUserBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExtraPayment extends Model
{
    use SyncsUserBalance;

    protected $fillable = [
        'user_id',
        'current_payment_id',
        'amount'
    ];

    public function currentPayment(): BelongsTo
    {
        return $this->belongsTo(CurrentPayment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
