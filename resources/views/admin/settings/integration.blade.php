@extends('layouts.base')
@section('title', 'توکن سرویس‌ها')
@section('page-title', 'تنظیمات ارتباط با سرویس‌ها')
@section('content')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <strong>توکن مشترک سرویس‌ها</strong>
                @if($hasServiceToken)
                    <span class="badge text-bg-success">تعریف شده</span>
                @else
                    <span class="badge text-bg-danger">تعریف نشده</span>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted">
                    این توکن برای تمام سرویس‌های سامانه استفاده و با قالب
                    <code dir="ltr">Authorization: Bearer TOKEN</code>
                    به سرور مقصد ارسال می‌شود. مقدار توکن در بانک اطلاعاتی رمزنگاری شده و پس از ذخیره دوباره نمایش داده نمی‌شود.
                </p>

                <form method="POST" action="{{ route('admin.settings.integration.update') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label" for="service_token">
                        {{ $hasServiceToken ? 'توکن جدید' : 'توکن سرویس‌ها' }}
                    </label>
                    <input
                        class="form-control @error('service_token') is-invalid @enderror"
                        id="service_token"
                        name="service_token"
                        type="password"
                        dir="ltr"
                        autocomplete="new-password"
                        maxlength="4096"
                        @required(! $hasServiceToken)
                    >
                    @error('service_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        توکن را بدون عبارت Bearer وارد کنید.@if($hasServiceToken) برای حفظ مقدار فعلی، این کادر را خالی بگذارید.@endif
                    </div>

                    <button class="btn btn-primary mt-4" type="submit">
                        <i class="bi bi-shield-lock"></i>
                        ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
