<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiRequestLogResource;
use App\Models\ApiRequestLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ApiRequestLogController extends Controller
{
    public function index(Request $request)
    {
        $viewerTimezone = trim((string) $request->string('viewer_timezone'));

        $filters = [
            'search' => trim((string) $request->string('search')),
            'action' => trim((string) $request->string('action')),
            'endpoint' => trim((string) $request->string('endpoint')),
            'method' => trim((string) $request->string('method')),
            'datetime_from' => trim((string) $request->string('datetime_from')),
            'datetime_to' => trim((string) $request->string('datetime_to')),
            'viewer_timezone' => $viewerTimezone,
        ];

        $baseQuery = ApiRequestLog::query()
            ->with('user')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('api_request_logs.action', 'like', '%' . $search . '%')
                        ->orWhere('api_request_logs.endpoint', 'like', '%' . $search . '%')
                        ->orWhere('api_request_logs.method', 'like', '%' . $search . '%')
                        ->orWhere('api_request_logs.request_timezone', 'like', '%' . $search . '%')
                        ->orWhere('api_request_logs.user_id', $search)
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('users.name', 'like', '%' . $search . '%')
                                ->orWhere('users.telegram', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['endpoint'] !== '', fn (Builder $query) => $query->where('endpoint', $filters['endpoint']))
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', strtoupper($filters['method'])))
            ->when($filters['datetime_from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $this->resolveDatetimeBoundary(
                $filters['datetime_from'],
                $viewerTimezone,
            )))
            ->when($filters['datetime_to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $this->resolveDatetimeBoundary(
                $filters['datetime_to'],
                $viewerTimezone,
            )));

        $logs = (clone $baseQuery)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $timezoneStats = (clone $baseQuery)
            ->selectRaw('COALESCE(request_timezone, ?) as timezone_label, COUNT(*) as hits', ['Не указана'])
            ->groupBy('timezone_label')
            ->orderByDesc('hits')
            ->limit(10)
            ->get()
            ->map(fn (ApiRequestLog $log) => [
                'timezone' => $log->timezone_label,
                'hits' => (int) $log->hits,
            ])
            ->values();

        $overview = [
            'total' => (clone $baseQuery)->count(),
            'unique_users' => (clone $baseQuery)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'timezone_count' => (clone $baseQuery)
                ->whereNotNull('request_timezone')
                ->distinct('request_timezone')
                ->count('request_timezone'),
        ];

        return $this->inertia('ApiRequestLogs/Index', [
            'filters' => $filters,
            'logs' => ApiRequestLogResource::collection($logs)->toArray($request),
            'timezone_stats' => $timezoneStats,
            'overview' => $overview,
            'viewer_timezone' => $viewerTimezone,
            'actions' => ApiRequestLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->values(),
            'endpoints' => ApiRequestLog::query()
                ->select('endpoint')
                ->distinct()
                ->orderBy('endpoint')
                ->pluck('endpoint')
                ->values(),
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        ]);
    }

    private function resolveDatetimeBoundary(string $datetime, string $viewerTimezone): CarbonImmutable
    {
        $timezone = $viewerTimezone !== '' ? $viewerTimezone : config('app.timezone');
        $boundary = CarbonImmutable::parse($datetime, $timezone);

        return $boundary->utc();
    }
}
