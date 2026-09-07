@extends('layouts.base')
@section('title','تعرفه سرویس‌ها')
@section('page-title','تعرفه و صورتحساب')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-1">تعرفه سرویس‌های استعلام</h5>
            <div class="text-muted small">مبلغ هر اجرای موفق مشخص می‌شود؛ اجرای ناموفق از کیف پول کاربر کسر نخواهد شد.</div>
        </div>
        <span class="badge text-bg-light">واحد: {{ config('billing.currency_label') }}</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.billing.update') }}">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>سرویس</th><th>کد سرویس</th><th>وضعیت</th><th style="min-width:220px">مبلغ هر استعلام موفق</th></tr></thead>
                    <tbody>
                    @forelse($services as $service)
                        <tr>
                            <td><strong>{{ $service->name }}</strong>@if($service->category)<div class="text-muted small">{{ $service->category }}</div>@endif</td>
                            <td dir="ltr"><code>{{ $service->slug }}</code></td>
                            <td>{{ $service->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td>
                                <div class="input-group">
                                    <input class="form-control @error('prices.'.$service->id) is-invalid @enderror" type="number" min="0" step="1" name="prices[{{ $service->id }}]" value="{{ old('prices.'.$service->id, $service->price_amount) }}" required>
                                    <span class="input-group-text">{{ config('billing.currency_label') }}</span>
                                </div>
                                @error('prices.'.$service->id)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">هنوز سرویسی تعریف نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($services->isNotEmpty())
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> ذخیره تعرفه‌ها</button>
            @endif
        </form>
    </div>
</div>
@endsection
