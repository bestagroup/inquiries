<?php

namespace App\Http\Controllers\User;

use App\Actions\ServiceRequests\DispatchServiceRequest;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Services\AuditLogger;
use App\Services\RemoteServices\DynamicInputValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class ServiceRequestController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            $query = request()->user()->serviceRequests()->select(['id', 'uuid', 'user_id', 'service_id', 'status', 'attempt_count', 'last_http_status', 'created_at'])->with('service:id,name');

            return DataTables::eloquent($query)
                ->addColumn('service_name', fn (ServiceRequest $r) => $r->service?->name)
                ->editColumn('status', fn (ServiceRequest $r) => __('statuses.'.$r->status->value))
                ->editColumn('created_at', fn (ServiceRequest $r) => $r->created_at?->format('Y-m-d H:i:s'))
                ->addColumn('action', fn (ServiceRequest $r) => '<a class="btn btn-sm btn-outline-primary" href="'.route('requests.show', $r).'">مشاهده</a>')
                ->rawColumns(['action'])->toJson();
        }

        return view('user.requests.index');
    }

    public function store(
        Request $request,
        RemoteService $service,
        DynamicInputValidator $validator,
        DispatchServiceRequest $dispatcher,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->authorizeService($service);
        $input = $validator->validate($service, (array) $request->input('input', []));
        $serviceRequest = ServiceRequest::query()->create([
            'user_id' => $request->user()->id,
            'service_id' => $service->id,
            'input_payload' => $input,
            'status' => 'pending',
        ]);
        try {
            $attempt = $dispatcher->dispatch($serviceRequest);
            $audit->write('request.created', $serviceRequest, ['attempt_id' => $attempt?->id, 'queued' => $attempt === null]);

            if (! $attempt) {
                return redirect()->route('requests.show', $serviceRequest)
                    ->with('success', 'درخواست ثبت شد و در صف پردازش قرار گرفت. نتیجه این صفحه به‌صورت خودکار بروزرسانی می‌شود.');
            }

            return redirect()->route('requests.show', $serviceRequest)
                ->with($attempt->status->value === 'succeeded' ? 'success' : 'error', $attempt->status->value === 'succeeded' ? 'استعلام با موفقیت انجام شد.' : 'استعلام انجام شد اما پاسخ موفق دریافت نشد.');
        } catch (Throwable $exception) {
            report($exception);
            $serviceRequest->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            $audit->write('request.execution_rejected', $serviceRequest, ['reason' => class_basename($exception)]);

            return redirect()->route('requests.show', $serviceRequest)->with('error', $exception->getMessage());
        }
    }

    public function show(ServiceRequest $request): View
    {
        $this->authorizeOwner($request);
        $request->load('service.fields');

        return view('user.requests.show', [
            'serviceRequest' => $request,
            'latestAttempt' => $request->attempts()->first(),
            'attempts' => $request->attempts()->paginate(20)->withQueryString(),
        ]);
    }

    public function attempt(ServiceRequest $request, ServiceRequestAttempt $attempt): View
    {
        $this->authorizeOwner($request);
        abort_unless($attempt->service_request_id === $request->id, 404);
        $request->load('service.fields');

        return view('requests.attempt', [
            'serviceRequest' => $request,
            'attempt' => $attempt,
            'backRoute' => route('requests.show', $request),
        ]);
    }

    public function update(
        Request $httpRequest,
        ServiceRequest $request,
        DynamicInputValidator $validator,
        DispatchServiceRequest $dispatcher,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->authorizeOwner($request);
        $this->assertCanReexecute($request);
        $input = $validator->validate($request->service, (array) $httpRequest->input('input', []));
        $previousInput = $request->input_payload;
        $previousStatus = $request->status;
        $previousToken = $request->execution_token;
        $request->update(['input_payload' => $input]);

        try {
            $attempt = $dispatcher->dispatch($request->fresh(['service', 'user']));
            $audit->write('request.updated_and_executed', $request, ['attempt_id' => $attempt?->id, 'queued' => $attempt === null]);

            if (! $attempt) {
                return redirect()->route('requests.show', $request)
                    ->with('success', 'اطلاعات درخواست بروزرسانی شد و اجرای جدید در صف قرار گرفت.');
            }

            return redirect()->route('requests.show', $request)->with($attempt->status->value === 'succeeded' ? 'success' : 'error', 'درخواست بروزرسانی و مجدداً اجرا شد.');
        } catch (Throwable $exception) {
            report($exception);
            $request->update([
                'input_payload' => $previousInput,
                'status' => $previousStatus,
                'execution_token' => $previousToken,
            ]);

            return redirect()->route('requests.show', $request)->with('error', $exception->getMessage());
        }
    }

    public function refresh(ServiceRequest $request, DispatchServiceRequest $dispatcher, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->assertCanReexecute($request);
        $previousStatus = $request->status;
        $previousToken = $request->execution_token;
        try {
            $attempt = $dispatcher->dispatch($request->fresh(['service', 'user']));
            $audit->write('request.reexecuted', $request, ['attempt_id' => $attempt?->id, 'queued' => $attempt === null]);

            if (! $attempt) {
                return redirect()->route('requests.show', $request)
                    ->with('success', 'اجرای مجدد درخواست در صف پردازش قرار گرفت.');
            }

            return redirect()->route('requests.show', $request)->with($attempt->status->value === 'succeeded' ? 'success' : 'error', 'درخواست مجدداً اجرا شد.');
        } catch (Throwable $exception) {
            report($exception);
            $request->update(['status' => $previousStatus, 'execution_token' => $previousToken]);

            return redirect()->route('requests.show', $request)->with('error', $exception->getMessage());
        }
    }

    private function authorizeService(RemoteService $service): void
    {
        abort_unless(
            $service->is_active && request()->user()->services()->whereKey($service->id)->wherePivot('is_active', true)->exists(),
            403,
            'این سرویس به شما تخصیص داده نشده است.'
        );
    }

    private function assertCanReexecute(ServiceRequest $request): void
    {
        $service = $request->service;
        $assigned = request()->user()->services()
            ->whereKey($request->service_id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless(
            $request->status !== ServiceRequestStatus::Pending
                && $service && ! $service->trashed() && $service->is_active && $service->allow_resubmit && $assigned,
            403,
            'این درخواست در حال پردازش است یا اجرای مجدد آن در حال حاضر مجاز نیست.'
        );
    }

    private function authorizeOwner(ServiceRequest $request): void
    {
        abort_unless($request->user_id === request()->user()->id, 403, 'این درخواست متعلق به شما نیست.');
    }
}
