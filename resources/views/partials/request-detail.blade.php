@php
    $latest = $latestAttempt;
    $inputFields = $serviceRequest->service->inputFields->keyBy('key');
    $isAdmin = auth()->user()->isAdmin();
@endphp

@if($serviceRequest->status->value === 'pending')
    <div class="alert alert-info d-flex align-items-center gap-2" role="status">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span>درخواست در حال پردازش است؛ نتیجه این صفحه به‌صورت خودکار بروزرسانی می‌شود.</span>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><strong>خلاصه درخواست</strong></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">شناسه</dt><dd class="col-7 text-break" dir="ltr">{{ $serviceRequest->uuid }}</dd>
                    <dt class="col-5">سرویس</dt><dd class="col-7">{{ $serviceRequest->service?->name }}</dd>
                    @if($isAdmin)<dt class="col-5">کاربر</dt><dd class="col-7">{{ $serviceRequest->user?->name }}</dd>@endif
                    <dt class="col-5">وضعیت</dt><dd class="col-7">{{ __('statuses.'.$serviceRequest->status->value) }}</dd>
                    <dt class="col-5">تعداد اجرا</dt><dd class="col-7">{{ $serviceRequest->attempt_count }}</dd>
                    <dt class="col-5">آخرین HTTP</dt><dd class="col-7">{{ $serviceRequest->last_http_status ?? '-' }}</dd>
                    <dt class="col-5">مدت پاسخ</dt><dd class="col-7">{{ $serviceRequest->last_duration_ms ? $serviceRequest->last_duration_ms.' ms' : '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><strong>ورودی فعلی درخواست</strong></div>
            <div class="card-body">
                <div class="row g-2">
                    @forelse((array)$serviceRequest->input_payload as $key => $value)
                        @php($field = $inputFields->get($key))
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">{{ $field?->label ?? $key }}</div>
                                <div class="fw-bold text-break">{{ $field?->is_sensitive ? '••••••••' : (is_bool($value) ? ($value ? 'بله' : 'خیر') : (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">این سرویس ورودی‌ای ندارد.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@if($latest)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>آخرین نتیجه</strong>
            <span class="badge {{ $latest->status->value === 'succeeded' ? 'badge-soft-success' : ($latest->status->value === 'running' ? 'badge-soft-warning' : 'badge-soft-danger') }}">{{ __('statuses.'.$latest->status->value) }}</span>
        </div>
        <div class="card-body">@include('partials.attempt-result', ['attempt' => $latest])</div>
    </div>
@elseif($serviceRequest->status->value === 'failed' && $serviceRequest->last_error)
    <div class="alert alert-danger">{{ $serviceRequest->last_error }}</div>
@endif

<div class="card">
    <div class="card-header"><strong>تاریخچه اجراها</strong></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>وضعیت</th><th>HTTP</th><th>مدت</th><th>شروع</th><th>خطا</th><th></th></tr></thead>
            <tbody>
            @forelse($attempts as $attempt)
                <tr>
                    <td>{{ $attempt->sequence }}</td>
                    <td>{{ __('statuses.'.$attempt->status->value) }}</td>
                    <td>{{ $attempt->http_status ?? '-' }}</td>
                    <td>{{ $attempt->duration_ms ? $attempt->duration_ms.' ms' : '-' }}</td>
                    <td>{{ $attempt->started_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="text-danger small">{{ $attempt->error_message }}</td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="{{ $isAdmin ? route('admin.requests.attempts.show', [$serviceRequest, $attempt]) : route('requests.attempts.show', [$serviceRequest, $attempt]) }}">مشاهده نتیجه</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">هنوز اجرایی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($attempts->hasPages())<div class="card-footer">{{ $attempts->links() }}</div>@endif
</div>

@if($serviceRequest->status->value === 'pending')
    @push('scripts')
        <script>window.setTimeout(() => window.location.reload(), 3000);</script>
    @endpush
@endif
