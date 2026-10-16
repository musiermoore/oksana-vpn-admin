<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\SubscriptionProfileHealthCheck;
use App\Models\XrayRouting;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;

final class SubscriptionProfileProbe
{
    private const UDP_PROTOCOLS = ['hysteria', 'hysteria2', 'hy2', 'wireguard', 'amneziawg'];

    private const LINK_PROTOCOLS = ['vless', 'trojan', 'shadowsocks'];

    public function __construct(
        private readonly XrayInboundProbeRunner $runner,
        private readonly ConnectJsonBuilder $jsonBuilder,
    ) {}

    /** @return array{status:string,results:array<string,mixed>,error:?string} */
    public function checkConnectNode(NormalizedNode $node): array
    {
        $protocol = mb_strtolower($node->protocol);

        if (in_array($protocol, self::UDP_PROTOCOLS, true)) {
            return $this->unsupported('UDP-based configs are excluded from TCP destination probes.');
        }

        if (! in_array($protocol, self::LINK_PROTOCOLS, true)) {
            return $this->unsupported('The current subscription probe does not support the '.$node->protocol.' link protocol.');
        }

        if (! $this->runner->isAvailable()) {
            return $this->unsupported('Xray Core is not installed for subscription probes.');
        }

        return $this->runner->checkSubscriptionNode($node, false);
    }

    /** @param array<string,mixed> $profile @return array{status:string,results:array<string,mixed>,error:?string} */
    public function checkJsonProfile(array $profile): array
    {
        $outbounds = is_array($profile['outbounds'] ?? null) ? $profile['outbounds'] : [];
        $udpOutbounds = collect($outbounds)
            ->pluck('protocol')
            ->map(fn (mixed $protocol): string => mb_strtolower((string) $protocol))
            ->intersect(self::UDP_PROTOCOLS)
            ->unique()
            ->values()
            ->all();

        if ($udpOutbounds !== []) {
            return $this->unsupported('Profile contains UDP-only outbound(s): '.implode(', ', $udpOutbounds).'.');
        }

        if (! $this->runner->isAvailable()) {
            return $this->unsupported('Xray Core is not installed for subscription probes.');
        }

        return $this->runner->checkGeneratedProfile($profile);
    }

    /** @return array{status:string,results:array<string,mixed>,error:?string} */
    public function checkExternalNode(NormalizedNode $node): array
    {
        $profile = $node->meta['json_profile'] ?? null;

        if (! is_array($profile) || ! is_array($profile['outbounds'] ?? null)) {
            if (! in_array(mb_strtolower($node->protocol), self::LINK_PROTOCOLS, true)) {
                return $this->checkConnectNode($node);
            }

            $profiles = json_decode($this->jsonBuilder->buildForSubscriptionType(
                [$node],
                XrayRouting::SUBSCRIPTION_CONNECT,
            )->content, true);
            $profile = is_array($profiles) ? ($profiles[0] ?? null) : null;

            if (is_array($profile)) {
                $profile['routing'] = [
                    'domainStrategy' => 'AsIs',
                    'rules' => [],
                ];
            }
        }

        if (! is_array($profile)) {
            throw new \RuntimeException('External config did not produce a JSON client profile.');
        }

        return $this->checkJsonProfile($profile);
    }

    /** @return array{status:string,results:array<string,mixed>,error:string} */
    private function unsupported(string $reason): array
    {
        return [
            'status' => SubscriptionProfileHealthCheck::STATUS_UNSUPPORTED,
            'results' => [],
            'error' => $reason,
        ];
    }
}
