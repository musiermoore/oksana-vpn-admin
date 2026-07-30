<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ShowReportsRequest;
use App\Services\Reports\ReportsService;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reports,
    ) {}

    public function index(ShowReportsRequest $request)
    {
        return $this->inertia('Reports/Index', $this->reports->build($request->toDto()));
    }
}
