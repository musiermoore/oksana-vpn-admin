<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\SubscriptionProfileHealthCheck;
use App\Models\User;
use App\Services\Subscriptions\UserSubscriptionService;

final class ConnectSubscriptionProfileHealthChecker
{
    public function __construct(
        private readonly UserSubscriptionService $subscriptions,
        private readonly SubscriptionProfileHealthRecorder $recorder,
    ) {}

    public function check(User $user): void
    {
        $this->recorder->runFlow('connect', fn () => $this->checkConnect($user));
        $this->recorder->runFlow('connect-v2', fn () => $this->checkConnectV2($user));
    }

    private function checkConnect(User $user): void
    {
        $nodes = $this->subscriptions->buildNamedNodes($user);

        foreach ($nodes as $node) {
            $this->recorder->queueProbe(
                'connect',
                $this->nodeKey($node),
                $this->nodeName($node),
                'connect-node',
                $node,
            );
        }

        $this->recorder->recordEmpty('connect', $nodes);
    }

    private function checkConnectV2(User $user): void
    {
        $decoded = json_decode(
            $this->subscriptions->buildConnectV2($user)->content,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new \RuntimeException('Подписка /connect-v2 не сформировала список профилей JSON.');
        }

        $profiles = $decoded;

        foreach ($profiles as $index => $profile) {
            if (! is_array($profile)) {
                $this->recorder->record(
                    'connect-v2',
                    sha1('malformed-profile|'.$index.'|'.json_encode($profile)),
                    'Некорректный профиль №'.($index + 1),
                    [
                        'status' => SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
                        'results' => [],
                        'error' => 'Запись /connect-v2 не является объектом профиля JSON.',
                    ],
                );

                continue;
            }

            $name = trim((string) ($profile['remarks'] ?? '')) ?: 'Профиль №'.($index + 1);
            $key = $this->profileKey($profile, 'connect-v2-'.$index);
            $this->recorder->queueProbe(
                'connect-v2',
                $key,
                $name,
                'json-profile',
                $profile,
            );
        }

        $this->recorder->recordEmpty('connect-v2', $profiles);
    }

    private function nodeName(NormalizedNode $node): string
    {
        return (string) ($node->meta['name'] ?? $node->serverName);
    }

    private function nodeKey(NormalizedNode $node): string
    {
        return sha1($node->sourceType.'|'.$node->id.'|'.$node->uri);
    }

    /** @param array<string,mixed> $profile */
    private function profileKey(array $profile, string $fallback): string
    {
        return sha1((string) ($profile['remarks'] ?? $fallback).'|'.json_encode($profile, JSON_UNESCAPED_SLASHES));
    }
}
