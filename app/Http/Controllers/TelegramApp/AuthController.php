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
use Closure;
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
        return $this->authenticationResponse(
            fn (): array => $this->authService->authenticate($request->toDto()),
            'Authentication failed.',
        );
    }

    public function authenticateWithPassword(AuthenticateTelegramAppPasswordRequest $request): JsonResponse
    {
        return $this->authenticationResponse(
            fn (): array => $this->authService->authenticateWithPassword($request->toDto()),
            'Authentication failed.',
        );
    }

    public function registerWithPassword(RegisterTelegramAppPasswordRequest $request): JsonResponse
    {
        return $this->authenticationResponse(
            fn (): array => $this->authService->registerWithPassword($request->toDto()),
            'Registration failed.',
            201,
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->userPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->bearerToken());

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return (new TelegramAppUserResource($user))->resolve();
    }

    /**
     * @param  Closure(): array{user: User, token: string, expires_at: mixed}  $authenticate
     */
    private function authenticationResponse(
        Closure $authenticate,
        string $failureMessage,
        int $successStatus = 200,
    ): JsonResponse {
        try {
            $result = $authenticate();
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => $failureMessage,
            ], 500);
        }

        $result['user']->setAttribute('referral_summary', $this->referrals->getSummary($result['user']));

        return response()->json([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            ...$this->userPayload($result['user']),
        ], $successStatus);
    }
}
