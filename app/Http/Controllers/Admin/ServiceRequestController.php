<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ServiceRequestController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            $query = ServiceRequest::query()->select(['id', 'uuid', 'user_id', 'service_id', 'status', 'attempt_count', 'last_http_status', 'created_at'])->with(['user:id,name', 'service:id,name']);

            return DataTables::eloquent($query)
                ->addColumn('user_name', fn (ServiceRequest $r) => $r->user?->name)
                ->addColumn('service_name', fn (ServiceRequest $r) => $r->service?->name)
                ->editColumn('status', fn (ServiceRequest $r) => __('statuses.'.$r->status->value))
                ->editColumn('created_at', fn (ServiceRequest $r) => $r->created_at?->format('Y-m-d H:i:s'))
                ->addColumn('action', fn (ServiceRequest $r) => '<a class="btn btn-sm btn-outline-primary" href="'.route('admin.requests.show', $r).'">مشاهده</a>')
                ->rawColumns(['action'])
                ->toJson();
        }

        $start = today()->subDays(6);
        $counts = ServiceRequest::query()
            ->selectRaw('DATE(created_at) as request_day, last_http_status, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupByRaw('DATE(created_at), last_http_status')
            ->get();

        $statuses = $counts->pluck('last_http_status')
            ->unique()
            ->sortBy(fn ($status) => $status === null ? PHP_INT_MAX : (int) $status)
            ->map(function ($status) use ($counts): array {
                $code = $status === null ? null : (int) $status;
                $hue = match (true) {
                    $code === null => 220,
                    $code < 200 => 185,
                    $code < 300 => 145,
                    $code < 400 => 215,
                    $code < 500 => 38,
                    default => 3,
                };
                $lightness = $code === null ? 67 : 39 + ($code % 5) * 7;

                return [
                    'key' => $code === null ? 'none' : (string) $code,
                    'label' => $code === null ? 'بدون پاسخ HTTP' : 'HTTP '.$code,
                    'color' => "hsl({$hue} 66% {$lightness}%)",
                    'total' => (int) $counts->filter(fn ($row) => $row->last_http_status === $status)->sum('total'),
                ];
            })->values()->all();

        $countsByDay = $counts->groupBy('request_day');
        $chartDays = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $start->copy()->addDays($offset);
            $dayCounts = $countsByDay->get($day->toDateString(), collect());
            $total = (int) $dayCounts->sum('total');
            $segments = [];

            foreach ($statuses as $status) {
                $count = (int) ($dayCounts->first(fn ($row) => ($row->last_http_status === null ? 'none' : (string) $row->last_http_status) === $status['key'])?->total ?? 0);
                if ($count > 0) {
                    $segments[] = $status + ['count' => $count, 'percent' => $total > 0 ? 100 * $count / $total : 0];
                }
            }

            $chartDays[] = [
                'label' => $day->format('m/d'),
                'date' => $day->toDateString(),
                'total' => $total,
                'segments' => $segments,
                'is_today' => $offset === 6,
            ];
        }

        $chartMax = max(1, ...array_column($chartDays, 'total'));
        $chartSummary = [
            'total' => (int) $counts->sum('total'),
            'success' => (int) $counts->filter(fn ($row) => $row->last_http_status >= 200 && $row->last_http_status < 300)->sum('total'),
            'without_http' => (int) $counts->filter(fn ($row) => $row->last_http_status === null)->sum('total'),
        ];

        return view('admin.requests.index', compact('chartDays', 'chartMax', 'chartSummary', 'statuses'));
    }

    public function show(ServiceRequest $request): View
    {
        $request->load(['user', 'service.fields']);

        return view('admin.requests.show', [
            'serviceRequest' => $request,
            'latestAttempt' => $request->attempts()->first(),
            'attempts' => $request->attempts()->paginate(20)->withQueryString(),
        ]);
    }

    public function attempt(ServiceRequest $request, ServiceRequestAttempt $attempt): View
    {
        abort_unless($attempt->service_request_id === $request->id, 404);
        $request->load(['user', 'service.fields']);

        return view('requests.attempt', [
            'serviceRequest' => $request,
            'attempt' => $attempt,
            'backRoute' => route('admin.requests.show', $request),
        ]);
    }
}
