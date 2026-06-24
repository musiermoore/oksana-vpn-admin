<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiSubscriptionPackageResource;
use App\Http\Resources\TelegramApp\TelegramAppUserResource;
use App\Models\User;
use App\Services\Api\ApiUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly ApiUserService $users,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => (new TelegramAppUserResource($user))->resolve(),
        ]);
    }

    public function subscriptionPackages(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => ApiSubscriptionPackageResource::collection(
                $this->users->getSubscriptionPackages($user)
            )->resolve(),
        ]);
    }
}
