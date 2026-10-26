<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerCommandTemplate extends Model
{
    public const FORMAT_SCRIPT = 'script';
    public const FORMAT_BLOCKS = 'blocks';

    protected $fillable = [
        'name',
        'description',
        'format',
        'content',
        'timeout_seconds',
    ];

    protected function casts(): array
    {
        return [
            'timeout_seconds' => 'integer',
        ];
    }

    /** @return array<int, string> */
    public static function formats(): array
    {
        return [self::FORMAT_SCRIPT, self::FORMAT_BLOCKS];
    }
}
