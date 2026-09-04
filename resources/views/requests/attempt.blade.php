@extends('layouts.base')
@section('title', 'نتیجه اجرای شماره '.$attempt->sequence)
@section('page-title', 'جزئیات اجرای درخواست')
@section('content')
@php($inputFields = $serviceRequest->service->inputFields->keyBy('key'))
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ $backRoute }}"><i class="bi bi-arrow-right"></i> بازگشت به درخواست</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-header"><strong>مشخصات اجرا</strong></div><div class="card-body small"><dl class="row mb-0">
            <dt class="col-5">سرویس</dt><dd class="col-7">{{ $serviceRequest->service?->name }}</dd>
            @if(auth()->user()->isAdmin())<dt class="col-5">کاربر</dt><dd class="col-7">{{ $serviceRequest->user?->name }}</dd>@endif
            <dt class="col-5">شماره اجرا</dt><dd class="col-7">{{ $attempt->sequence }}</dd>
            <dt class="col-5">وضعیت</dt><dd class="col-7">{{ __('statuses.'.$attempt->status->value) }}</dd>
            <dt class="col-5">HTTP</dt><dd class="col-7">{{ $attempt->http_status ?? '-' }}</dd>
            <dt class="col-5">مدت</dt><dd class="col-7">{{ $attempt->duration_ms ? $attempt->duration_ms.' ms' : '-' }}</dd>
            <dt class="col-5">زمان</dt><dd class="col-7">{{ $attempt->started_at?->format('Y-m-d H:i:s') }}</dd>
        </dl></div></div>
    </div>
    <div class="col-xl-8">
        <div class="card h-100"><div class="card-header"><strong>ورودی همین اجرا</strong></div><div class="card-body"><div class="row g-2">
            @forelse((array)$attempt->request_payload as $key => $value)
                @php($field = $inputFields->get($key))
                <div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-muted small">{{ $field?->label ?? $key }}</div><div class="fw-bold text-break">{{ $field?->is_sensitive ? '••••••••' : (is_bool($value) ? ($value ? 'بله' : 'خیر') : (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))) }}</div></div></div>
            @empty
                <div class="text-muted">این اجرا ورودی‌ای نداشته است.</div>
            @endforelse
        </div></div></div>
    </div>
</div>
<div class="card"><div class="card-header"><strong>پاسخ ذخیره‌شده</strong></div><div class="card-body">@include('partials.attempt-result')</div></div>
@endsection
