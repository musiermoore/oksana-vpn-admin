<?php

namespace App\Services;

use App\Models\User;
use App\Services\ExternalSubscriptions\Incy\IncyCryptLinkEncoder;
use App\Services\ExternalSubscriptions\V2RayTun\V2RayTunCryptLinkEncoder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VlessDeepLinkService
{
    public function __construct(
        private readonly IncyCryptLinkEncoder $incyLinkEncoder,
        private readonly V2RayTunCryptLinkEncoder $v2rayTunLinkEncoder,
    ) {}

    private const CLIENT_RESPONSE_KEYS = [
        'happ' => 'happ_deep_link',
        'v2rayn' => 'v2rayn_deeplink',
        'v2rayng' => 'v2rayng_deeplink',
        'v2box' => 'v2raybox_deeplink',
        'sing-box' => 'sing_box_deeplink',
        'hiddify' => 'hiddify_deeplink',
            'v2raytun' => 'v2raytun_deeplink',
        'incy' => 'incy_deeplink',
    ];

    /**
     * @param  array{token: string}  $parameters
     * @return array<string, string>
     */
    public function getRouteLinks(array $parameters): array
    {
        return $this->getRouteLinksForRoute('vless.deep-link', $parameters);
    }

    /**
     * @param  array{token: string}  $parameters
     * @return array<string, string>
     */
    public function getRouteLinksForRoute(string $routeName, array $parameters): array
    {
        $links = [];

        foreach (self::CLIENT_RESPONSE_KEYS as $client => $responseKey) {
            $links[$responseKey] = config('vless.domain').route($routeName, [
                ...$parameters,
                'client' => $client,
            ], absolute: false);
        }

        return $links;
    }

    public function getConnectUrl(User $user, ?string $client = null): string
    {
        return $this->getConnectUrlForRoute($user, 'vless.connect', $client);
    }

    public function getConnectV2Url(User $user, ?string $client = null): string
    {
        $url = rtrim((string) config('vless.public_domain'), '/')
            .'/start?token='.urlencode((string) $user->uuid);

        if ($client !== null) {
            $url .= '&app='.urlencode($client);
        }

        return $url;
    }

    public function getConnectV2RouteLinks(User $user): array
    {
        return $this->getPublicDeepLinkLinks($user);
    }

    public function getConnectV2DeepLinks(User $user): array
    {
        return $this->getPublicDeepLinkLinks($user);
    }

    /**
     * @return array<string, string>
     */
    private function getPublicDeepLinkLinks(User $user): array
    {
        $links = [];
        $domain = rtrim((string) config('vless.public_domain'), '/');

        foreach (self::CLIENT_RESPONSE_KEYS as $client => $responseKey) {
            $links[$responseKey] = $domain.'/deep-link/'.$client.'?token='.urlencode((string) $user->uuid);
        }

        return $links;
    }

    public function getConnectUrlForRoute(User $user, string $routeName, ?string $client = null): string
    {
        $parameters = $this->getConnectRouteParameters($user);
        $format = $this->resolveClientFormat($client);

        if ($format !== null) {
            $parameters['format'] = $format;
        }

        return $this->buildUrl($routeName, $parameters);
    }

    /**
     * @return array{tg: string, i: string}
     */
    public function getConnectRouteParameters(User $user): array
    {
        return [
            'tg' => Crypt::encrypt($user->telegram_id),
            'i' => Crypt::encrypt((string) $user->id),
        ];
    }

    /**
     * @return array{token: string}
     */
    public function getDeepLinkRouteParameters(User $user): array
    {
        return [
            'token' => Crypt::encrypt([
                'tg' => $user->telegram_id,
                'i' => (string) $user->id,
            ]),
        ];
    }

    public function resolveRedirectUrl(string $client, string $subscriptionLink): ?string
    {
        return match ($client) {
            'happ' => $this->getHappDeepLink($subscriptionLink),
            'v2rayn' => $this->buildInstallSubLink('v2rayn', $subscriptionLink),
            'v2rayng' => $this->buildInstallSubLink('v2rayng', $subscriptionLink),
            'v2box' => $this->buildInstallSubLink('v2box', $subscriptionLink),
            'sing-box' => 'sing-box://import-remote-profile?url='.urlencode($subscriptionLink),
            'hiddify' => 'hiddify://import/'.$subscriptionLink,
            'v2raytun' => $this->v2rayTunLinkEncoder->encode($subscriptionLink),
            'incy' => $this->incyLinkEncoder->encode($subscriptionLink),
            default => null,
        };
    }

    private function buildInstallSubLink(string $scheme, string $subscriptionLink): string
    {
        return "{$scheme}://install-sub?url=".urlencode($subscriptionLink);
    }

    private function getHappDeepLink(string $subscriptionLink): string
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->post('https://crypto.happ.su/api-v2.php', [
                'url' => $subscriptionLink,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to generate Happ deep link.');
        }

        $deepLink = trim((string) ($response->json('encrypted_link') ?? $response->json('url') ?? $response->body()));

        if ($deepLink === '') {
            throw new RuntimeException('Happ deep link response is empty.');
        }

        return $deepLink;
    }

    private function resolveClientFormat(?string $client): ?string
    {
        return match ($client) {
            'hiddify' => 'clash',
            'sing-box' => 'sing-box',
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function buildUrl(string $routeName, array $parameters): string
    {
        return config('vless.domain').route($routeName, $parameters, absolute: false);
    }
}
