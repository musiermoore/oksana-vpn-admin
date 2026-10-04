<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

class XrayJsonProfileNormalizer
{
    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function normalizeProfile(array $profile): array
    {
        return $this->normalizeRouting($this->normalizeOutbounds($this->normalizeInbounds($profile)));
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function normalizeInbounds(array $profile): array
    {
        if (! is_array($profile['inbounds'] ?? null)) {
            return $profile;
        }

        $profile['inbounds'] = array_map(function (mixed $inbound): mixed {
            if (! is_array($inbound)) {
                return $inbound;
            }

            if (($inbound['settings'] ?? null) === []) {
                $inbound['settings'] = (object) [];
            }

            return $inbound;
        }, $profile['inbounds']);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function normalizeOutbounds(array $profile): array
    {
        if (! is_array($profile['outbounds'] ?? null)) {
            return $profile;
        }

        $profile['outbounds'] = array_map(function (mixed $outbound): mixed {
            if (! is_array($outbound) || ! is_array($outbound['streamSettings'] ?? null)) {
                return $outbound;
            }

            if (($outbound['streamSettings']['network'] ?? null) === 'tcp'
                && ($outbound['streamSettings']['tcpSettings'] ?? null) === []
            ) {
                $outbound['streamSettings']['tcpSettings'] = (object) [];
            }

            return $outbound;
        }, $profile['outbounds']);

        return $profile;
    }

    /**
     * Xray expects balancer strategy settings to be an object, even when the
     * strategy has no options. Database JSON casts and imported profiles may
     * represent an empty object as an empty PHP array, which would otherwise
     * be encoded as `[]` and rejected by Xray.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function normalizeRouting(array $profile): array
    {
        if (! is_array($profile['routing']['balancers'] ?? null)) {
            return $profile;
        }

        $profile['routing']['balancers'] = array_map(function (mixed $balancer): mixed {
            if (! is_array($balancer) || ! is_array($balancer['strategy'] ?? null)) {
                return $balancer;
            }

            if (($balancer['strategy']['settings'] ?? null) === []) {
                $balancer['strategy']['settings'] = (object) [];
            }

            return $balancer;
        }, $profile['routing']['balancers']);

        return $profile;
    }
}
