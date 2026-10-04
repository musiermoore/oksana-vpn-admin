<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayCustomConfigRoute extends Model
{
    protected $fillable = [
        'xray_custom_config_id', 'name', 'rules', 'target_type', 'target_tag', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['rules' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(XrayCustomConfig::class, 'xray_custom_config_id');
    }
}
