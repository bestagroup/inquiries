@extends('layouts.base')
@section('title','جزئیات درخواست')
@section('page-title','جزئیات درخواست')
@section('content')
@php
    $canReexecute = $serviceRequest->status->value !== 'pending'
        && $serviceRequest->service
        && $serviceRequest->service->allow_resubmit
        && auth()->user()->canUseService($serviceRequest->service);
    $currentPrice = (int) ($serviceRequest->service?->price_amount ?? 0);
    $wallet = auth()->user()->isAdmin() ? null : auth()->user()->wallet;
    $canAfford = auth()->user()->isAdmin() || $currentPrice <= 0 || (($wallet?->availableBalance() ?? 0) >= $currentPrice);
@endphp

<div class="d-flex flex-wrap gap-2 mb-3">
@if($canReexecute)
    <form method="POST" action="{{ route('requests.refresh', $serviceRequest) }}" data-inquiry-submit>
        @csrf
        <button class="btn btn-primary" data-inquiry-submit-button @disabled(!$canAfford) @unless($canAfford) data-permanent-disabled="1" @endunless><i class="bi bi-arrow-repeat"></i> اجرای مجدد با همین اطلاعات</button>
    </form>
@endif
<a class="btn btn-outline-secondary" href="{{ route('requests.index') }}">بازگشت به لیست</a>
</div>

@if($canReexecute && !$canAfford)
    <div class="alert alert-warning">موجودی قابل استفاده کیف پول برای اجرای مجدد این استعلام کافی نیست. تعرفه فعلی: {{ number_format($currentPrice) }} {{ config('billing.currency_label') }}.</div>
@endif

@include('partials.request-detail')

@if($canReexecute)
<div class="card mt-3">
    <div class="card-header"><strong>ویرایش ورودی و اجرای مجدد</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('requests.update', $serviceRequest) }}" enctype="multipart/form-data" data-inquiry-submit>
            @csrf
            @method('PUT')
            <div class="row g-3">
                @foreach($serviceRequest->service->inputFields as $field)
                    @php
                        $wasSensitive = in_array($field->key, $serviceRequest->sensitive_input_keys ?? [], true);
                        $current = ($field->type->value === 'image' || $field->is_sensitive || $wasSensitive)
                            ? null
                            : old('input.'.$field->key, data_get($serviceRequest->input_payload, $field->key));
                    @endphp
                    <div class="col-md-6">
                        <label class="form-label">{{ $field->label }} @if($field->is_required)<span class="text-danger">*</span>@endif</label>
                        @if($field->type->value === 'select')
                            <select class="form-select" name="input[{{ $field->key }}]" @required($field->is_required)><option value="">انتخاب کنید</option>@foreach((array) $field->options as $option)<option value="{{ $option }}" @selected((string) $current === (string) $option)>{{ $option }}</option>@endforeach</select>
                        @elseif($field->type->value === 'boolean')
                            <select class="form-select" name="input[{{ $field->key }}]" @required($field->is_required)><option value="" @selected($current === null || $current === '')>انتخاب کنید</option><option value="1" @selected((string) $current === '1')>بله</option><option value="0" @selected((string) $current === '0')>خیر</option></select>
                        @elseif($field->type->value === 'image')
                            <input class="form-control @error($field->key) is-invalid @enderror" type="file" name="input[{{ $field->key }}]" accept="image/jpeg,image/png,image/webp" @required($field->is_required)>
                            <div class="form-text">برای ویرایش و اجرای مجدد، تصویر را دوباره انتخاب کنید. حداکثر حجم: {{ number_format(config('remote_services.max_image_kilobytes')) }} کیلوبایت.</div>
                            @error($field->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @else
                            <input class="form-control" name="input[{{ $field->key }}]" type="{{ ($field->is_sensitive || $wasSensitive) ? 'password' : ($field->type->value === 'number' ? 'number' : ($field->type->value === 'date' ? 'date' : 'text')) }}" value="{{ $current }}" @if($field->type->value === 'number' && !$wasSensitive) step="any" @endif @if($field->is_sensitive || $wasSensitive) autocomplete="new-password" @endif @required($field->is_required)>
                            @if($field->is_sensitive || $wasSensitive)<div class="form-text">برای تغییر و اجرا، مقدار حساس را مجدداً وارد کنید.</div>@endif
                        @endif
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary mt-3" data-inquiry-submit-button @disabled(!$canAfford) @unless($canAfford) data-permanent-disabled="1" @endunless>ذخیره و اجرا</button>
        </form>
    </div>
</div>
@endif
@endsection
