<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramApp\ReportBootstrapDiagnosticRequest;
use App\Services\TelegramApp\TelegramMiniAppBootstrapDiagnosticService;
use Illuminate\Http\JsonResponse;

class DiagnosticController extends Controller
{
    public function __construct(
        private readonly TelegramMiniAppBootstrapDiagnosticService $diagnostics,
    ) {}

    public function bootstrap(ReportBootstrapDiagnosticRequest $request): JsonResponse
    {
        $this->diagnostics->report($request->toDto(), $request);

        return response()->json([
            'message' => 'Diagnostic accepted.',
        ], 202);
    }
}
