<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'ip',
        'app_path'
    ];

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    public function getSlugCodeAttribute(): string
    {
        return str($this->code)->slug()->lower();
    }

    public function getSshCommandAttribute(): string
    {
        $sshKeyPath = '/var/www/html/storage/ssh_key';

        return "$(which ssh) -i $sshKeyPath -o BatchMode=yes -o StrictHostKeyChecking=no root@{$this->ip}";
    }
}
