<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Giveaways\GiveawayDrawService;
use App\Services\Giveaways\GiveawayReadService;
use App\Repositories\GiveawayRepository;
use Illuminate\Console\Command;

class SyncGiveawaysCommand extends Command
{
    protected $signature = 'giveaways:sync';

    protected $description = 'Активирует начавшиеся розыгрыши и завершает истёкшие.';

    public function __construct(
        private readonly GiveawayReadService $reader,
        private readonly GiveawayRepository $giveaways,
        private readonly GiveawayDrawService $draws,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->reader->syncLifecycleStates();

        foreach ($this->giveaways->dueForDraw() as $giveaway) {
            $this->draws->draw($giveaway);
        }

        return self::SUCCESS;
    }
}
