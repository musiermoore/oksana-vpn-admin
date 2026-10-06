<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiRequestLogResource;
use App\Models\ApiRequestLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiRequestLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $baseQuery = $this->filteredQuery($filters);
        $logs = (clone $baseQuery)->latest()->paginate(50)->withQueryString();

        return $this->inertia('ApiRequestLogs/Index', [
            'filters' => $filters,
            'logs' => ApiRequestLogResource::collection($logs)->response()->getData(true),
            'top_users' => $this->topUsers($baseQuery),
            'timezone_stats' => $this->timezoneStats($baseQuery),
            'overview' => $this->overview($baseQuery),
            'viewer_timezone' => $filters['viewer_timezone'],
            ...$this->filterOptions(),
        ]);
    }

    /**
     * @return array{search:string, action:string, endpoint:string, method:string, datetime_from:string, datetime_to:string, viewer_timezone:string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'action' => trim((string) $request->string('action')),
            'endpoint' => trim((string) $request->string('endpoint')),
            'method' => trim((string) $request->string('method')),
            'datetime_from' => trim((string) $request->string('datetime_from')),
            'datetime_to' => trim((string) $request->string('datetime_to')),
            'viewer_timezone' => trim((string) $request->string('viewer_timezone')),
        ];
    }

    /**
     * @param  array{search:string, action:string, endpoint:string, method:string, datetime_from:string, datetime_to:string, viewer_timezone:string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return ApiRequestLog::query()
            ->with('user')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery
                        ->whereAny([
                            'api_request_logs.action',
                            'api_request_logs.endpoint',
                            'api_request_logs.method',
                            'api_request_logs.ip_address',
                            'api_request_logs.forwarded_for',
                            'api_request_logs.user_agent',
                            'api_request_logs.request_timezone',
                        ], 'like', '%'.$search.'%')
                        ->orWhere('api_request_logs.user_id', $search)
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('users.name', 'like', '%'.$search.'%')
                                ->orWhere('users.telegram', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['endpoint'] !== '', fn (Builder $query) => $query->where('endpoint', $filters['endpoint']))
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', strtoupper($filters['method'])))
            ->when($filters['datetime_from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $this->resolveDatetimeBoundary(
                $filters['datetime_from'],
                $filters['viewer_timezone'],
            )))
            ->when($filters['datetime_to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $this->resolveDatetimeBoundary(
                $filters['datetime_to'],
                $filters['viewer_timezone'],
            )));
    }

    private function topUsers(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->selectRaw('user_id, COUNT(*) as hits')
            ->with('user')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('hits')
            ->limit(10)
            ->get()
            ->map(fn (ApiRequestLog $log) => [
                'user_id' => $log->user_id,
                'hits' => (int) $log->hits,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'full_name' => $log->user->full_name,
                    'telegram' => $log->user->telegram,
                    'edit_url' => route('users.edit', $log->user),
                ] : null,
            ])
            ->values();
    }

    private function timezoneStats(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
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
    }

    /** @return array{total:int, unique_users:int, timezone_count:int} */
    private function overview(Builder $baseQuery): array
    {
        return [
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
    }

    /** @return array<string, mixed> */
    private function filterOptions(): array
    {
        return [
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
        ];
    }

    private function resolveDatetimeBoundary(string $datetime, string $viewerTimezone): CarbonImmutable
    {
        $timezone = $viewerTimezone !== '' ? $viewerTimezone : config('app.timezone');
        $boundary = CarbonImmutable::parse($datetime, $timezone);

        return $boundary->utc();
    }
}
