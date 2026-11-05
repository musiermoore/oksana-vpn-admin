<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

class XrayJsonProfileNormalizer
{
    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function normalizeProfile(array $profile, ?string $client = null): array
    {
        return $this->normalizeRouting(
            $this->normalizePolicy($this->normalizeOutbounds($this->normalizeInbounds($profile), $client)),
        );
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
    private function normalizeOutbounds(array $profile, ?string $client): array
    {
        if (! is_array($profile['outbounds'] ?? null)) {
            return $profile;
        }

        $profile['outbounds'] = array_map(function (mixed $outbound) use ($client): mixed {
            if (! is_array($outbound) || ! is_array($outbound['streamSettings'] ?? null)) {
                return $outbound;
            }

            if (($outbound['streamSettings']['network'] ?? null) === 'tcp'
                && ($outbound['streamSettings']['tcpSettings'] ?? null) === []
            ) {
                $outbound['streamSettings']['tcpSettings'] = (object) [];
            }

            if (($outbound['streamSettings']['wsSettings']['headers'] ?? null) === []) {
                $outbound['streamSettings']['wsSettings']['headers'] = (object) [];
            }

            if ($client === 'v2raytun'
                && is_array($outbound['streamSettings']['grpcSettings'] ?? null)
            ) {
                unset($outbound['streamSettings']['grpcSettings']['mode']);
            }

            if (($outbound['streamSettings']['network'] ?? null) === 'xhttp'
                && is_string($outbound['streamSettings']['xhttpSettings']['extra'] ?? null)
            ) {
                $extra = json_decode($outbound['streamSettings']['xhttpSettings']['extra']);

                if (json_last_error() === JSON_ERROR_NONE && is_object($extra)) {
                    $outbound['streamSettings']['xhttpSettings']['extra'] = $extra;
                }
            }

            return $outbound;
        }, $profile['outbounds']);

        return $profile;
    }

    /**
     * Xray expects policy levels to be an object keyed by level number. PHP's
     * associative JSON decoding represents both JSON objects and arrays as
     * arrays, so cast the levels map back to an object before encoding.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function normalizePolicy(array $profile): array
    {
        if (is_array($profile['policy'] ?? null) && is_array($profile['policy']['levels'] ?? null)) {
            $profile['policy']['levels'] = (object) $profile['policy']['levels'];
        }

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
        if (is_array($profile['routing']['balancers'] ?? null)) {
            $profile['routing']['balancers'] = array_map(function (mixed $balancer): mixed {
                if (! is_array($balancer) || ! is_array($balancer['strategy'] ?? null)) {
                    return $balancer;
                }

                if (($balancer['strategy']['settings'] ?? null) === []) {
                    $balancer['strategy']['settings'] = (object) [];
                }

                return $balancer;
            }, $profile['routing']['balancers']);
        }

        if (is_array($profile['routing']['rules'] ?? null)) {
            $profile['routing']['rules'] = array_map(function (mixed $rule): mixed {
                if (! is_array($rule)) {
                    return $rule;
                }

                if (is_array($rule['port'] ?? null)) {
                    $ports = array_values(array_filter(
                        array_map(static fn (mixed $port): string => trim((string) $port), $rule['port']),
                        static fn (string $port): bool => $port !== '',
                    ));
                    $rule['port'] = implode(',', $ports);
                }

                if (array_key_exists('ip', $rule)) {
                    $rule['ip'] = $this->replaceRussiaGeoIpRule($rule['ip']);
                }

                return $rule;
            }, $profile['routing']['rules']);
        }

        return $profile;
    }

    private function replaceRussiaGeoIpRule(mixed $ipRules): mixed
    {
        $replace = static fn (mixed $rule): mixed => is_string($rule)
            && strtolower(trim($rule)) === 'geoip:ru'
                ? 'geoip:direct'
                : $rule;

        if (is_array($ipRules)) {
            return array_map($replace, $ipRules);
        }

        if (is_string($ipRules)) {
            return implode(',', array_map($replace, explode(',', $ipRules)));
        }

        return $ipRules;
    }
}
