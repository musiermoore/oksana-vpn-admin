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

        $user = User::query()
            ->where('uuid', $request->string('token')->toString())
            ->first();

        abort_unless($user instanceof User, 404);

        $request->setUserResolver(static fn (): User => $user);
        $devices->recordConnection($user, $request);
        $client = mb_strtolower($request->string('app')->toString());
        $userAgent = mb_strtolower((string) $request->userAgent());

        if ($client === '' && str_contains($userAgent, 'v2raytun')) {
            $client = 'v2raytun';
        }

        if ($client === '' && str_contains($userAgent, 'happ')) {
            $client = 'happ';
        }

        $subscription = $builder->build(
            $user,
            $subscriptionType->load('items'),
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
}
