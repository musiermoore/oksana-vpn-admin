<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayCustomConfigUserPrice extends Model
{
    protected $fillable = [
        'xray_custom_config_user_id',
        'price',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'xray_custom_config_user_id' => 'integer',
            'price' => 'float',
            'effective_from' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(XrayCustomConfigUser::class, 'xray_custom_config_user_id');
    }
}
