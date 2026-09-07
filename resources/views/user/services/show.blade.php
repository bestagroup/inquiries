@extends('layouts.base')
@section('title', $service->name)
@section('page-title', $service->name)

@section('content')
@php
    $servicePrice = (int) $service->price_amount;
    $availableBalance = $wallet->availableBalance();
    $canAfford = $servicePrice <= 0 || $availableBalance >= $servicePrice;
@endphp
<div class="service-run-page">
    <header class="page-heading service-run-heading">
        <div>
            <a class="page-back-link" href="{{ route('services.index') }}"><i class="bi bi-arrow-right"></i> بازگشت به سرویس‌ها</a>
            <div class="service-run-title">
                <span><i class="bi {{ $service->icon ?: 'bi-hdd-network' }}"></i></span>
                <div>
                    <div class="service-run-meta">{{ $service->category ?: 'سرویس استعلام' }} <i></i> فعال</div>
                    <h1>{{ $service->name }}</h1>
                </div>
            </div>
            <p>{{ $service->description }}</p>
        </div>
    </header>

    @unless($canAfford)
        <div class="alert alert-warning d-flex align-items-center gap-2"><i class="bi bi-wallet2"></i><div><strong>موجودی کیف پول کافی نیست.</strong><div class="small">برای این استعلام {{ number_format($servicePrice) }} {{ config('billing.currency_label') }} نیاز است و موجودی قابل استفاده شما {{ number_format($availableBalance) }} {{ config('billing.currency_label') }} است.</div></div></div>
    @endunless

    <div class="service-run-layout">
        <section class="service-run-form">
            <div class="service-run-section-heading">
                <div><span>۱</span><div><h2>اطلاعات مورد نیاز</h2><p>موارد زیر را با دقت و مطابق مدارک رسمی وارد کنید.</p></div></div>
                <small><b>*</b> فیلد اجباری</small>
            </div>

            <form method="POST" action="{{ route('requests.store', $service) }}" data-inquiry-submit>
                @csrf
                <div class="row g-3">
                    @forelse($service->inputFields as $field)
                        @php
                            $hasDigitsRule = collect((array) $field->validation_rules)->contains(fn ($rule) => str_starts_with($rule, 'digits'));
                            $fieldId = 'service-input-'.$field->id;
                        @endphp
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $fieldId }}">{{ $field->label }} @if($field->is_required)<b>*</b>@endif</label>
                            @if($field->type->value === 'select')
                                <select class="form-select @error($field->key) is-invalid @enderror" id="{{ $fieldId }}" name="input[{{ $field->key }}]" @required($field->is_required)>
                                    <option value="">انتخاب کنید</option>
                                    @foreach((array) $field->options as $option)<option value="{{ $option }}" @selected(old('input.'.$field->key) === $option)>{{ $option }}</option>@endforeach
                                </select>
                            @elseif($field->type->value === 'boolean')
                                <select class="form-select @error($field->key) is-invalid @enderror" id="{{ $fieldId }}" name="input[{{ $field->key }}]" @required($field->is_required)>
                                    <option value="">انتخاب کنید</option><option value="1" @selected((string) old('input.'.$field->key) === '1')>بله</option><option value="0" @selected((string) old('input.'.$field->key) === '0')>خیر</option>
                                </select>
                            @elseif($field->is_sensitive)
                                <div class="sensitive-input">
                                    <input class="form-control @error($field->key) is-invalid @enderror" id="{{ $fieldId }}" type="password" name="input[{{ $field->key }}]" inputmode="{{ $hasDigitsRule ? 'numeric' : 'text' }}" autocomplete="off" @required($field->is_required)>
                                    <button type="button" data-password-toggle="{{ $fieldId }}" aria-label="نمایش مقدار"><i class="bi bi-eye"></i></button>
                                </div>
                            @else
                                <input class="form-control @error($field->key) is-invalid @enderror" id="{{ $fieldId }}" type="{{ $field->type->value === 'number' ? 'number' : ($field->type->value === 'date' ? 'date' : 'text') }}" name="input[{{ $field->key }}]" value="{{ old('input.'.$field->key, $field->default_value) }}" @if($field->type->value === 'number') step="any" @endif @required($field->is_required)>
                            @endif
                            @error($field->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @empty
                        <div class="col-12"><div class="service-no-input"><i class="bi bi-info-circle"></i> این سرویس به اطلاعات ورودی نیاز ندارد.</div></div>
                    @endforelse
                </div>

                <div class="service-run-submit">
                    <div><i class="bi bi-shield-check"></i><span><strong>ارسال امن اطلاعات</strong><small>کسر هزینه فقط پس از دریافت پاسخ موفق انجام می‌شود.</small></span></div>
                    <button class="btn btn-primary" type="submit" data-inquiry-submit-button @disabled(!$canAfford)><i class="bi bi-send"></i> ثبت و اجرای استعلام</button>
                </div>
            </form>
        </section>

        <aside class="service-run-sidebar">
            <div class="service-info-card">
                <h2>مشخصات سرویس</h2>
                <dl>
                    <div><dt>وضعیت</dt><dd class="text-success"><i class="bi bi-circle-fill"></i> فعال</dd></div>
                    <div><dt>تعرفه</dt><dd>{{ $servicePrice > 0 ? number_format($servicePrice).' '.config('billing.currency_label') : 'رایگان' }}</dd></div>
                    <div><dt>موجودی قابل استفاده</dt><dd>{{ number_format($availableBalance) }} {{ config('billing.currency_label') }}</dd></div>
                    <div><dt>تعداد ورودی</dt><dd>{{ $service->inputFields->count() }} مورد</dd></div>
                    <div><dt>مهلت پاسخ</dt><dd>تا {{ $service->timeout_seconds }} ثانیه</dd></div>
                    <div><dt>کد سرویس</dt><dd><code>{{ $service->slug }}</code></dd></div>
                </dl>
                <a class="btn btn-sm btn-outline-primary w-100" href="{{ route('wallet.show') }}"><i class="bi bi-wallet2"></i> مشاهده کیف پول</a>
            </div>
            <div class="service-help-card"><i class="bi bi-headset"></i><div><strong>نیاز به راهنمایی دارید؟</strong><p>در صورت ابهام درباره اطلاعات ورودی، پیش از ثبت درخواست با مدیر سامانه در ارتباط باشید.</p></div></div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', event => {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) return;
    const input = document.getElementById(button.dataset.passwordToggle);
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.setAttribute('aria-label', reveal ? 'مخفی‌کردن مقدار' : 'نمایش مقدار');
    button.querySelector('i').className = `bi ${reveal ? 'bi-eye-slash' : 'bi-eye'}`;
});
</script>
@endpush
