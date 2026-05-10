<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function inertia(string $component, array $props = []): Response
    {
        return Inertia::render($component, $props);
    }
}
