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
        return $this->normalizeOutbounds($this->normalizeInbounds($profile));
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

            if (mb_strtolower((string) ($inbound['protocol'] ?? '')) === 'http'
                && ($inbound['settings'] ?? null) === []
            ) {
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
}
