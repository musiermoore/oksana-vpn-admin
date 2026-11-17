<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionType;
use App\Models\User;
use App\Services\SubscriptionMetadataService;
use App\Services\Subscriptions\SubscriptionTypeBuilderService;
use App\Services\UserConnectedDeviceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SubscriptionOutputController extends Controller
{
    public function show(
        Request $request,
        SubscriptionType $subscriptionType,
        SubscriptionTypeBuilderService $builder,
        SubscriptionMetadataService $metadata,
        UserConnectedDeviceService $devices,
    ): Response {
        abort_unless($subscriptionType->is_active, 404);

        $subscriptionType->load('items');
        $client = $this->resolveClient($request);

        if (! $subscriptionType->requires_user_token) {
            $subscription = $builder->buildStatic(
                $subscriptionType,
                $client !== '' ? $client : null,
            );
            $response = response($subscription->content);

            foreach ($metadata->buildStaticHeaders(
                $subscription->fileExtension,
                $subscription->contentType,
                (string) $subscriptionType->name,
            ) as $name => $value) {
                $response->header($name, $value);
            }

            return $response;
        }

        $user = User::query()
            ->where('uuid', $request->string('token')->toString())
            ->first();

        abort_unless($user instanceof User, 404);

        $request->setUserResolver(static fn (): User => $user);
        $devices->recordConnection($user, $request);

        $subscription = $builder->build(
            $user,
            $subscriptionType,
            $client !== '' ? $client : null,
        );
        $response = response($subscription->content);

        foreach ($metadata->buildHeaders(
            $user,
            $subscription->fileExtension,
            $subscription->contentType,
            (string) $subscriptionType->name,
        ) as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    private function resolveClient(Request $request): string
    {
        $client = mb_strtolower($request->string('app')->toString());
        $userAgent = mb_strtolower((string) $request->userAgent());

        if ($client === '' && str_contains($userAgent, 'v2raytun')) {
            return 'v2raytun';
        }

        if ($client === '' && str_contains($userAgent, 'happ')) {
            return 'happ';
        }

        return $client;
    }
}
