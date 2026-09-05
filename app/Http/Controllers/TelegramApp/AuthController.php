<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramApp\AuthenticateTelegramAppPasswordRequest;
use App\Http\Requests\TelegramApp\AuthenticateTelegramAppRequest;
use App\Http\Requests\TelegramApp\RegisterTelegramAppPasswordRequest;
use App\Http\Resources\TelegramApp\TelegramAppUserResource;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\TelegramApp\TelegramMiniAppAuthService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly TelegramMiniAppAuthService $authService,
        private readonly ReferralService $referrals,
    ) {}

    public function authenticate(AuthenticateTelegramAppRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->authenticate($request->toDto());
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Authentication failed.',
            ], 500);
        }

        $result['user']->setAttribute('referral_summary', $this->referrals->getSummary($result['user']));

        return response()->json([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'user' => (new TelegramAppUserResource($result['user']))->resolve(),
        ]);
    }

    public function authenticateWithPassword(AuthenticateTelegramAppPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->authenticateWithPassword($request->toDto());
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Authentication failed.',
            ], 500);
        }

        $result['user']->setAttribute('referral_summary', $this->referrals->getSummary($result['user']));

        return response()->json([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'user' => (new TelegramAppUserResource($result['user']))->resolve(),
        ]);
    }

    public function registerWithPassword(RegisterTelegramAppPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerWithPassword($request->toDto());
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Registration failed.',
            ], 500);
        }

        $result['user']->setAttribute('referral_summary', $this->referrals->getSummary($result['user']));

        return response()->json([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'user' => (new TelegramAppUserResource($result['user']))->resolve(),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => (new TelegramAppUserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->bearerToken());

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
