<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AdminSearchService;
use Illuminate\Http\Request;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private readonly AdminSearchService $search,
    ) {}

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('q'));

        return $this->inertia('Search/Index', [
            'query' => $query,
            'results' => $this->search->search($query),
        ]);
    }
}
