@extends('layouts.base')
@section('title', $user->exists ? 'ویرایش کاربر' : 'کاربر جدید')
@section('page-title', $user->exists ? 'ویرایش کاربر' : 'تعریف کاربر جدید')

@section('content')
@php
    $walletBalance = old('wallet_balance', $user->wallet?->balance ?? 0);
    $reservedBalance = (int) ($user->wallet?->reserved_balance ?? 0);
    $selected = old('service_ids', $selectedServices);
@endphp

<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><strong>اطلاعات کاربر</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">نام و نام خانوادگی</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">ایمیل</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                    <div class="col-md-6"><label class="form-label">موبایل</label><input class="form-control" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                    <div class="col-md-6"><label class="form-label">وضعیت</label><select class="form-select" name="is_active"><option value="1" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '1')>فعال</option><option value="0" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '0')>غیرفعال</option></select></div>
                    <div class="col-md-6"><label class="form-label">رمز عبور {{ $user->exists ? '(در صورت تغییر)' : '' }}</label><input class="form-control" type="password" name="password" {{ $user->exists ? '' : 'required' }} autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label">تکرار رمز عبور</label><input class="form-control" type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }} autocomplete="new-password"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"><strong>کیف پول کاربر</strong><span class="badge text-bg-light">{{ config('billing.currency_label') }}</span></div>
                <div class="card-body">
                    <label class="form-label" for="wallet-balance">موجودی کیف پول</label>
                    <div class="input-group">
                        <input id="wallet-balance" class="form-control @error('wallet_balance') is-invalid @enderror" type="number" min="0" step="1" name="wallet_balance" value="{{ $walletBalance }}" required>
                        <span class="input-group-text">{{ config('billing.currency_label') }}</span>
                    </div>
                    @error('wallet_balance')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text">هر تغییر در این عدد به‌عنوان تراکنش مدیریتی در گردش کیف پول ثبت می‌شود.</div>
                    @if($reservedBalance > 0)
                        <div class="alert alert-warning mt-3 mb-0 py-2">{{ number_format($reservedBalance) }} {{ config('billing.currency_label') }} برای استعلام‌های در حال پردازش رزرو شده است؛ موجودی را کمتر از این مبلغ نمی‌توان تنظیم کرد.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><strong>دسترسی به سرویس‌ها</strong></div>
                <div class="card-body">
                    <p class="text-muted small">کاربر فقط سرویس‌های انتخاب‌شده را مشاهده و اجرا می‌کند.</p>
                    @forelse($services as $service)
                        <label class="d-flex align-items-center justify-content-between border rounded p-3 mb-2">
                            <span><strong>{{ $service->name }}</strong>@unless($service->is_active)<small class="text-danger d-block">غیرفعال</small>@endunless</span>
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, array_map('intval', (array) $selected), true))>
                        </label>
                    @empty
                        <div class="text-muted">هنوز سرویسی تعریف نشده است.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="submit">ذخیره اطلاعات</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">انصراف</a>
    </div>
</form>
@endsection
