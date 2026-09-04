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

        return view('admin.requests.index');
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
