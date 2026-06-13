<?php

namespace App\Console\Commands;

use App\Jobs\PullVlessConfigsForServerJob;
use App\Models\Server;
use Illuminate\Console\Command;

class PullVlessConfigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vless-configs:pull';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Vless configs from servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $servers = Server::query()
            ->whereNotNull([
                'panel_link',
                'panel_username',
                'panel_password'
            ])
            ->vless()
            ->get();

        foreach ($servers as $server) {
            PullVlessConfigsForServerJob::dispatch($server->id);
        }

        return self::SUCCESS;
    }
}
