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
        'link_host',
        'app_path',
        'ssh_private_key',
        'ssh_public_key',
        'is_vless',
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

        return "ssh -i {$sshKeyPath} -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=15 root@{$this->ip} 2>&1";
    }

    public function getHost()
    {
        return $this->link_host ?: $this->ip;
    }
}
