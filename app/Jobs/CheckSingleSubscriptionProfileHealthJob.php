<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\Subscription\NormalizedNode;
use App\Services\InboundHealthCheckSettings;
use App\Services\SubscriptionProfileHealthRecorder;
use App\Services\SubscriptionProfileProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckSingleSubscriptionProfileHealthJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    public int $uniqueFor = 1800;

    /** @param array<string,mixed>|NormalizedNode $subject */
    public function __construct(
        public readonly string $flow,
        public readonly string $profileKey,
        public readonly string $profileName,
        public readonly string $mode,
        public readonly array|NormalizedNode $subject,
    ) {
        $this->onQueue('slow');
    }

    public function uniqueId(): string
    {
        return $this->flow.':'.$this->profileKey;
    }

    public function handle(
        SubscriptionProfileProbe $probe,
        SubscriptionProfileHealthRecorder $recorder,
        InboundHealthCheckSettings $settings,
    ): void {
        if (! $settings->enabled()) {
            return;
        }

        $recorder->recordProbe($this->flow, $this->profileKey, $this->profileName, fn (): array => match ($this->mode) {
            'connect-node' => $this->subject instanceof NormalizedNode
                ? $probe->checkConnectNode($this->subject)
                : throw new \RuntimeException('Queued connect profile payload is invalid.'),
            'external-node' => $this->subject instanceof NormalizedNode
                ? $probe->checkExternalNode($this->subject)
                : throw new \RuntimeException('Queued external profile payload is invalid.'),
            'json-profile' => is_array($this->subject)
                ? $probe->checkJsonProfile($this->subject)
                : throw new \RuntimeException('Queued JSON profile payload is invalid.'),
            default => throw new \RuntimeException('Unknown subscription health probe mode.'),
        });
    }
}
