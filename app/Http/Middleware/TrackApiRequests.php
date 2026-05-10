<?php

namespace App\Http\Middleware;

use App\Jobs\StoreApiRequestLogJob;
use App\Support\ApiRequestLogPayloadFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackApiRequests
{
    public function __construct(
        private readonly ApiRequestLogPayloadFactory $payloadFactory,
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            try {
                StoreApiRequestLogJob::dispatch(
                    $this->payloadFactory->fromRequest($request, $response),
                );
            } catch (Throwable $throwable) {
                report($throwable);
            }
        }
    }
}
