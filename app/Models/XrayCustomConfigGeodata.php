<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayCustomConfigGeodata extends Model
{
    protected $table = 'xray_custom_config_geodata';

    protected $fillable = ['client_key', 'geodata_id'];

    public function geodata(): BelongsTo
    {
        return $this->belongsTo(XrayRoutingGeodata::class, 'geodata_id');
    }
}
