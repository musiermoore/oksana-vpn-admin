<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboard,
    ) {}

    public function index(): Response
    {
        return $this->inertia('Dashboard/Index', $this->dashboard->build());
    }
}
