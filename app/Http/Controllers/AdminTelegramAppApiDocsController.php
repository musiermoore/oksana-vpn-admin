<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminTelegramAppApiDocsController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        return Inertia::render('ApiDocs/TelegramMiniApp', [
            'specUrl' => route('api-docs.telegram-mini-app.spec'),
        ]);
    }

    public function spec(Request $request): BinaryFileResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        return response()->file(base_path('docs/openapi/telegram-mini-app.yaml'), [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
